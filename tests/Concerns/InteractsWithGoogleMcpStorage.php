<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use JsonException;

trait InteractsWithGoogleMcpStorage
{
    protected function configureGoogleMcpStoragePaths(): void
    {
        $baseDirectory = storage_path('framework/testing/google-mcp');

        File::ensureDirectoryExists($baseDirectory);

        $tokenFile = "{$baseDirectory}/google-calendar-tokens.json";
        $idempotencyFile = "{$baseDirectory}/idempotency.json";

        config()->set('services.google_mcp.token_file', $tokenFile);
        config()->set('services.google_mcp.idempotency_file', $idempotencyFile);

        File::delete([$tokenFile, $idempotencyFile]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     *
     * @throws JsonException
     */
    protected function writeGoogleAccessToken(array $overrides = []): void
    {
        $tokenFile = (string) config('services.google_mcp.token_file');

        File::ensureDirectoryExists(dirname($tokenFile));

        $payload = array_merge([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'token_type' => 'Bearer',
        ], $overrides);

        File::put($tokenFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
