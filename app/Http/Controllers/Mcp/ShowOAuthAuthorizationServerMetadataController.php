<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;

class ShowOAuthAuthorizationServerMetadataController
{
    public function __invoke(): JsonResponse
    {
        return response()->json($this->metadataPayload());
    }

    /**
     * @return array{
     *     issuer: string,
     *     authorization_endpoint: mixed,
     *     token_endpoint: mixed,
     *     jwks_uri: mixed,
     *     response_types_supported: array<int, string>,
     *     grant_types_supported: array<int, string>,
     *     code_challenge_methods_supported: array<int, string>,
     *     scopes_supported: mixed,
     *     token_endpoint_auth_methods_supported: array<int, string>
     * }
     */
    protected function metadataPayload(): array
    {
        return [
            'issuer' => 'https://accounts.google.com',
            'authorization_endpoint' => config('services.google_mcp.oauth_authorization_endpoint'),
            'token_endpoint' => config('services.google_mcp.oauth_token_endpoint'),
            'jwks_uri' => config('services.google_mcp.oauth_jwks_uri'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => config('services.google_mcp.mcp_required_scopes', []),
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        ];
    }
}
