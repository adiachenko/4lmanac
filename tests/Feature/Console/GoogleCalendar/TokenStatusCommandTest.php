<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
});

test('shows warning when no shared google token is stored', function (): void {
    $this->artisan('app:google-calendar:token:status')
        ->expectsOutputToContain('No Google token is stored yet. Run app:google-calendar:bootstrap first.')
        ->assertExitCode(Command::SUCCESS);
});

test('shows shared google token table when token payload exists', function (): void {
    $expiresAt = CarbonImmutable::now()->addHour()->toIso8601String();

    $this->writeGoogleAccessToken([
        'access_token' => 'token-access',
        'refresh_token' => 'token-refresh',
        'expires_at' => $expiresAt,
        'scope' => 'https://www.googleapis.com/auth/calendar',
    ]);

    $tokenFile = (string) config('services.google_mcp.token_file');

    $this->artisan('app:google-calendar:token:status')
        ->expectsTable(['Field', 'Value'], [
            ['token_file', $tokenFile],
            ['has_access_token', 'yes'],
            ['has_refresh_token', 'yes'],
            ['expires_at', $expiresAt],
            ['expired', 'no'],
            ['scope', 'https://www.googleapis.com/auth/calendar'],
        ])
        ->assertExitCode(Command::SUCCESS);
});
