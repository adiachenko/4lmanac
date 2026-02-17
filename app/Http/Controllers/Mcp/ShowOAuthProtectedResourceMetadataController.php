<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;

class ShowOAuthProtectedResourceMetadataController
{
    public function __invoke(?string $path = ''): JsonResponse
    {
        $normalizedPath = is_string($path) ? $path : '';

        return response()->json([
            'resource' => url("/{$normalizedPath}"),
            'authorization_servers' => ['https://accounts.google.com'],
            'scopes_supported' => config('services.google_mcp.mcp_required_scopes', []),
        ]);
    }
}
