<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use Carbon\CarbonImmutable;
use JsonException;

class IdempotencyStore
{
    protected string $filePath;

    public function __construct()
    {
        $this->filePath = $this->resolveFilePath(
            'services.google_mcp.idempotency_file',
            storage_path('app/mcp/idempotency.json'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(): array<string, mixed>  $resolver
     * @return array{replayed: bool, response: array<string, mixed>}
     */
    public function run(string $operation, string $idempotencyKey, array $payload, callable $resolver): array
    {
        return $this->withLock(function (array $store) use ($operation, $idempotencyKey, $payload, $resolver): array {
            $entries = $this->normalizeEntries($store['entries'] ?? []);
            $entries = $this->withoutExpiredEntries($entries);

            $compositeKey = $this->entryKey($operation, $idempotencyKey, $payload);

            if (isset($entries[$compositeKey]['response']) && is_array($entries[$compositeKey]['response'])) {
                /** @var array<string, mixed> $response */
                $response = $entries[$compositeKey]['response'];

                return [
                    'data' => ['entries' => $entries],
                    'result' => [
                        'replayed' => true,
                        'response' => $response,
                    ],
                ];
            }

            $response = $resolver();

            $entries[$compositeKey] = [
                'expires_at' => CarbonImmutable::now()->addDay()->toIso8601String(),
                'response' => $response,
            ];

            return [
                'data' => ['entries' => $entries],
                'result' => [
                    'replayed' => false,
                    'response' => $response,
                ],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function entryKey(string $operation, string $idempotencyKey, array $payload): string
    {
        return hash('sha256', json_encode([
            'operation' => $operation,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, array<string, mixed>>
     */
    protected function withoutExpiredEntries(array $entries): array
    {
        $now = CarbonImmutable::now();

        return array_filter($entries, static function (array $entry) use ($now): bool {
            $expiresAt = $entry['expires_at'] ?? null;

            if (! is_string($expiresAt)) {
                return false;
            }

            return CarbonImmutable::parse($expiresAt)->greaterThan($now);
        });
    }

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): array{data: array<string, mixed>, result: T}  $callback
     * @return T
     */
    protected function withLock(callable $callback): mixed
    {
        $directory = dirname($this->filePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($this->filePath, 'c+');

        throw_unless(is_resource($handle), GoogleCalendarException::class, 'UPSTREAM_ERROR', 500, 'Unable to open idempotency storage file.');

        try {
            flock($handle, LOCK_EX);

            $content = stream_get_contents($handle);
            $payload = $this->decode($content);
            $result = $callback($payload);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $this->encode($result['data']));
            fflush($handle);

            return $result['result'];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(string|false $content): array
    {
        if (! is_string($content) || trim($content) === '') {
            return [];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GoogleCalendarException('UPSTREAM_ERROR', 500, 'Unable to encode idempotency payload.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $key => $entry) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $normalizedEntry = [];

            foreach ($entry as $entryKey => $entryValue) {
                if (! is_string($entryKey)) {
                    continue;
                }

                $normalizedEntry[$entryKey] = $entryValue;
            }

            $normalized[$key] = $normalizedEntry;
        }

        return $normalized;
    }

    protected function resolveFilePath(string $configKey, string $defaultPath): string
    {
        $configuredPath = config($configKey, $defaultPath);

        if (! is_string($configuredPath) || trim($configuredPath) === '') {
            return $defaultPath;
        }

        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        return base_path($configuredPath);
    }

    protected function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return true;
        }

        return preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1;
    }
}
