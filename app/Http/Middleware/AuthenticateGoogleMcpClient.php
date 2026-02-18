<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGoogleMcpClient
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Missing bearer token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tokenInfoEndpoint = config('services.google_mcp.oauth_token_info_endpoint');
        $tokenInfoEndpoint = is_string($tokenInfoEndpoint) ? $tokenInfoEndpoint : 'https://oauth2.googleapis.com/tokeninfo';

        $response = Http::get($tokenInfoEndpoint, [
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid OAuth token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        $audienceCandidates = array_values(array_filter([
            is_string($payload['aud'] ?? null) ? $payload['aud'] : null,
            is_string($payload['azp'] ?? null) ? $payload['azp'] : null,
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));

        /** @var array<int, string> $allowedAudiences */
        $allowedAudiences = config('services.google_mcp.oauth_allowed_audiences', []);

        $hasAllowedAudience = false;

        foreach ($audienceCandidates as $audienceCandidate) {
            if (in_array($audienceCandidate, $allowedAudiences, true)) {
                $hasAllowedAudience = true;

                break;
            }
        }

        if (! $hasAllowedAudience) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'OAuth token audience is not allowed.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $scopeString = is_string($payload['scope'] ?? null)
            ? $payload['scope']
            : '';

        $tokenScopes = array_values(array_filter(explode(' ', $scopeString)));

        /** @var array<int, string> $requiredScopes */
        $requiredScopes = config('services.google_mcp.mcp_required_scopes', []);

        $missingScopes = array_values(array_diff($requiredScopes, $tokenScopes));

        if ($missingScopes !== []) {
            return response()->json([
                'error' => 'insufficient_scope',
                'message' => 'OAuth token is missing required scopes.',
                'missing_scopes' => $missingScopes,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
