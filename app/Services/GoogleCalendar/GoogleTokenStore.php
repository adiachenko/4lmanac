<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use Carbon\CarbonImmutable;
use JsonException;

class GoogleTokenStore
{
    protected string $filePath;

    public function __construct()
    {
        $this->filePath = $this->resolveFilePath(
            'services.google_mcp.token_file',
            storage_path('app/mcp/google-calendar-tokens.json'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->withLock(function (array $data): array {
            return [
                'data' => $data,
                'result' => $data,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     */
    public function write(array $tokenPayload): void
    {
        $this->withLock(function () use ($tokenPayload): array {
            return [
                'data' => $tokenPayload,
                'result' => null,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $tokenResponse
     * @return array<string, mixed>
     */
    public function mergeTokenResponse(array $tokenResponse): array
    {
        return $this->withLock(function (array $current) use ($tokenResponse): array {
            $existingRefreshToken = is_string($current['refresh_token'] ?? null)
                ? $current['refresh_token']
                : null;

            $refreshToken = is_string($tokenResponse['refresh_token'] ?? null)
                ? $tokenResponse['refresh_token']
                : $existingRefreshToken;

            $accessToken = $tokenResponse['access_token'] ?? $current['access_token'] ?? null;
            $expiresIn = $this->normalizeInteger($tokenResponse['expires_in'] ?? 0);
            $expiresAt = $expiresIn > 0
                ? CarbonImmutable::now()->addSeconds($expiresIn)->toIso8601String()
                : (is_string($current['expires_at'] ?? null) ? $current['expires_at'] : null);

            $merged = array_filter([
                'access_token' => is_string($accessToken) ? $accessToken : null,
                'refresh_token' => $refreshToken,
                'token_type' => is_string($tokenResponse['token_type'] ?? null) ? $tokenResponse['token_type'] : ($current['token_type'] ?? 'Bearer'),
                'scope' => is_string($tokenResponse['scope'] ?? null) ? $tokenResponse['scope'] : ($current['scope'] ?? null),
                'expires_at' => $expiresAt,
            ], static fn (mixed $value): bool => $value !== null);

            return [
                'data' => $merged,
                'result' => $merged,
            ];
        });
    }

    public function currentAccessToken(): ?string
    {
        $payload = $this->read();

        $accessToken = $payload['access_token'] ?? null;
        $expiresAt = $payload['expires_at'] ?? null;

        if (! is_string($accessToken) || ! is_string($expiresAt)) {
            return null;
        }

        $expiration = CarbonImmutable::parse($expiresAt);

        if ($expiration->lessThanOrEqualTo(CarbonImmutable::now()->addSeconds(30))) {
            return null;
        }

        return $accessToken;
    }

    public function refreshToken(): ?string
    {
        $payload = $this->read();

        return is_string($payload['refresh_token'] ?? null)
            ? $payload['refresh_token']
            : null;
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

        if (! is_resource($handle)) {
            throw new GoogleCalendarException('UPSTREAM_ERROR', 500, 'Unable to open Google token storage file.');
        }

        try {
            flock($handle, LOCK_EX);

            $content = stream_get_contents($handle);
            $data = $this->decode($content);
            $result = $callback($data);

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
            throw new GoogleCalendarException('UPSTREAM_ERROR', 500, 'Unable to encode Google token storage payload.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    protected function normalizeInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
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
