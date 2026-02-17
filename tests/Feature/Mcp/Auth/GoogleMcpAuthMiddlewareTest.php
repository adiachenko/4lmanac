<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.google_mcp.oauth_allowed_audiences', [
        'chatgpt-client.apps.googleusercontent.com',
        'claude-client.apps.googleusercontent.com',
    ]);
    config()->set('services.google_mcp.mcp_required_scopes', [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
    ]);
});

test('rejects requests without bearer token', function (): void {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'ping',
    ]);

    $response->assertUnauthorized();
    $response->assertHeader('WWW-Authenticate');
});

test('rejects requests with invalid audience', function (): void {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'unknown-client.apps.googleusercontent.com',
            'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
            'sub' => 'subject-1',
        ]),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
    ])->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'ping',
    ]);

    $response->assertUnauthorized();
});

test('rejects requests with missing required scopes', function (): void {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'chatgpt-client.apps.googleusercontent.com',
            'scope' => 'openid',
            'sub' => 'subject-1',
        ]),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
    ])->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'ping',
    ]);

    $response->assertForbidden();
});

test('accepts valid oauth token and serves mcp response', function (): void {
    Http::fake([
        'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'chatgpt-client.apps.googleusercontent.com',
            'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
            'sub' => 'subject-1',
            'email' => 'tester@example.com',
        ]),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
    ])->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new stdClass,
            'clientInfo' => [
                'name' => 'test-client',
                'version' => '1.0.0',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertHeader('MCP-Session-Id');
});

test('serves oauth metadata routes', function (): void {
    $resourceMetadata = $this->getJson('/.well-known/oauth-protected-resource/mcp');
    $authorizationMetadata = $this->getJson('/.well-known/oauth-authorization-server/mcp');

    $resourceMetadata->assertOk();
    $authorizationMetadata->assertOk();

    $resourceMetadata->assertJsonStructure([
        'resource',
        'authorization_servers',
        'scopes_supported',
    ]);

    $authorizationMetadata->assertJsonStructure([
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
        'response_types_supported',
        'grant_types_supported',
        'code_challenge_methods_supported',
        'scopes_supported',
    ]);
});
