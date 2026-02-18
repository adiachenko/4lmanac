<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();

    config()->set('services.google_mcp.oauth_token_endpoint', 'https://oauth2.googleapis.com/token');
    config()->set('services.google_mcp.oauth_client_id', 'test-client-id');
    config()->set('services.google_mcp.oauth_client_secret', 'test-client-secret');
    config()->set('services.google_mcp.oauth_redirect_uri', 'http://localhost/oauth/google/bootstrap/callback');

    File::ensureDirectoryExists(dirname(googleBootstrapStateFilePath()));
    File::delete(googleBootstrapStateFilePath());
});

afterEach(function (): void {
    File::delete(googleBootstrapStateFilePath());
});

test('returns unprocessable response when callback parameters are missing', function (): void {
    $response = $this->get('/oauth/google/bootstrap/callback');

    $response->assertStatus(422);
    $response->assertSeeText('Missing required OAuth callback parameters.');
});

test('returns unprocessable response when callback state does not match expected state', function (): void {
    writeGoogleBootstrapState('expected-state');

    $response = $this->get('/oauth/google/bootstrap/callback?state=wrong-state&code=auth-code');

    $response->assertStatus(422);
    $response->assertSeeText('Invalid OAuth state. Start bootstrap again.');

    expect(File::exists(googleBootstrapStateFilePath()))->toBeTrue();
});

test('returns unprocessable response when oauth token endpoint configuration is missing', function (): void {
    config()->set('services.google_mcp.oauth_token_endpoint', '');
    writeGoogleBootstrapState('expected-state');

    $response = $this->get('/oauth/google/bootstrap/callback?state=expected-state&code=auth-code');

    $response->assertStatus(422);
    $response->assertSeeText('Missing OAuth token endpoint configuration.');
});

test('stores merged token payload and clears bootstrap state after successful callback', function (): void {
    writeGoogleBootstrapState('expected-state');

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'token_type' => 'Bearer',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'expires_in' => 3600,
        ], 200),
    ]);

    $response = $this->get('/oauth/google/bootstrap/callback?state=expected-state&code=auth-code');

    $response->assertOk();
    $response->assertSeeText('Google Calendar bootstrap completed successfully. Shared tokens were stored.');

    $tokenFile = (string) config('services.google_mcp.token_file');
    expect(File::exists($tokenFile))->toBeTrue();

    /** @var array<string, mixed>|null $storedPayload */
    $storedPayload = json_decode((string) File::get($tokenFile), true);

    expect(is_array($storedPayload))->toBeTrue();
    expect($storedPayload['access_token'] ?? null)->toBe('new-access-token');
    expect($storedPayload['refresh_token'] ?? null)->toBe('new-refresh-token');

    expect(File::exists(googleBootstrapStateFilePath()))->toBeFalse();
});

function googleBootstrapStateFilePath(): string
{
    return storage_path('app/mcp/google-bootstrap-state.json');
}

function writeGoogleBootstrapState(string $state): void
{
    File::put(googleBootstrapStateFilePath(), json_encode([
        'state' => $state,
        'created_at' => now()->toIso8601String(),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
