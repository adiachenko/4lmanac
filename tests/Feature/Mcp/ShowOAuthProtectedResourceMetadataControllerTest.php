<?php

declare(strict_types=1);

test('returns oauth protected resource metadata payload with root resource url', function (): void {
    config()->set('services.google_mcp.mcp_required_scopes', [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
    ]);

    $response = $this->getJson('/.well-known/oauth-protected-resource');

    $response->assertOk();
    $response->assertJson([
        'resource' => url('/'),
        'authorization_servers' => ['https://accounts.google.com'],
        'scopes_supported' => [
            'openid',
            'https://www.googleapis.com/auth/userinfo.email',
        ],
    ]);
});

test('returns oauth protected resource metadata payload with normalized nested path', function (): void {
    $response = $this->getJson('/.well-known/oauth-protected-resource/mcp/tools');

    $response->assertOk();
    $response->assertJson([
        'resource' => url('/mcp/tools'),
        'authorization_servers' => ['https://accounts.google.com'],
    ]);
});
