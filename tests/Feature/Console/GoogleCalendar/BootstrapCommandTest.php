<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    File::ensureDirectoryExists(dirname(googleBootstrapStateFilePathForCommand()));
    File::delete(googleBootstrapStateFilePathForCommand());

    Str::createRandomStringsNormally();
});

afterEach(function (): void {
    File::delete(googleBootstrapStateFilePathForCommand());
    Str::createRandomStringsNormally();
});

test('fails when required oauth bootstrap configuration is missing', function (): void {
    config()->set('services.google_mcp.oauth_client_id', '');
    config()->set('services.google_mcp.oauth_redirect_uri', '');

    $this->artisan('app:google-calendar:bootstrap')
        ->expectsOutputToContain('Missing GOOGLE_OAUTH_CLIENT_ID or GOOGLE_OAUTH_REDIRECT_URI configuration.')
        ->assertExitCode(Command::FAILURE);
});

test('fails when oauth authorization endpoint configuration is missing', function (): void {
    config()->set('services.google_mcp.oauth_client_id', 'test-client-id');
    config()->set('services.google_mcp.oauth_redirect_uri', 'http://localhost/oauth/google/bootstrap/callback');
    config()->set('services.google_mcp.oauth_authorization_endpoint', '');

    $this->artisan('app:google-calendar:bootstrap')
        ->expectsOutputToContain('Missing GOOGLE OAuth authorization endpoint configuration.')
        ->assertExitCode(Command::FAILURE);
});

test('stores bootstrap state and prints authorization url', function (): void {
    config()->set('services.google_mcp.oauth_client_id', 'test-client-id');
    config()->set('services.google_mcp.oauth_redirect_uri', 'http://localhost/oauth/google/bootstrap/callback');
    config()->set('services.google_mcp.oauth_authorization_endpoint', 'https://accounts.google.com/o/oauth2/v2/auth');

    Str::createRandomStringsUsing(static fn (): string => 'fixed-bootstrap-state');

    $this->artisan('app:google-calendar:bootstrap')
        ->expectsOutputToContain('Open this URL in your browser to authorize the shared calendar account:')
        ->expectsOutputToContain('https://accounts.google.com/o/oauth2/v2/auth?')
        ->assertExitCode(Command::SUCCESS);

    /** @var array<string, mixed>|null $statePayload */
    $statePayload = json_decode((string) File::get(googleBootstrapStateFilePathForCommand()), true);

    expect(is_array($statePayload))->toBeTrue();
    expect($statePayload['state'] ?? null)->toBe('fixed-bootstrap-state');
    expect(is_string($statePayload['created_at'] ?? null))->toBeTrue();
});

function googleBootstrapStateFilePathForCommand(): string
{
    $configuredPath = config('services.google_mcp.bootstrap_state_file', storage_path('app/mcp/google-bootstrap-state.json'));

    return is_string($configuredPath) && trim($configuredPath) !== ''
        ? $configuredPath
        : storage_path('app/mcp/google-bootstrap-state.json');
}
