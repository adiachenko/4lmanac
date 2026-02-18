<?php

declare(strict_types=1);

test('returns oauth authorization server metadata payload', function (): void {
    config()->set('services.google_mcp.oauth_authorization_endpoint', 'https://accounts.google.com/o/oauth2/v2/auth');
    config()->set('services.google_mcp.oauth_token_endpoint', 'https://oauth2.googleapis.com/token');
    config()->set('services.google_mcp.oauth_jwks_uri', 'https://www.googleapis.com/oauth2/v3/certs');
    config()->set('services.google_mcp.mcp_required_scopes', [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
    ]);

    $response = $this->getJson('/.well-known/oauth-authorization-server/mcp');

    $response->assertOk();
    $response->assertJson([
        'issuer' => 'https://accounts.google.com',
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => [
            'openid',
            'https://www.googleapis.com/auth/userinfo.email',
        ],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
    ]);
});
