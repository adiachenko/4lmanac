<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use Illuminate\Support\Facades\Http;

class GoogleTokenRefresher
{
    public function __construct(
        protected GoogleTokenStore $tokenStore,
        protected GoogleCalendarErrorMapper $errorMapper,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function refreshAccessToken(): array
    {
        $refreshToken = $this->tokenStore->refreshToken();

        throw_if(! is_string($refreshToken) || $refreshToken === '', GoogleCalendarException::class, errorCode: 'GOOGLE_REAUTH_REQUIRED', statusCode: 401, message: 'Google refresh token is missing. Run the bootstrap flow again.');

        $tokenEndpoint = config('services.google_mcp.oauth_token_endpoint');
        $tokenEndpoint = is_string($tokenEndpoint) ? $tokenEndpoint : 'https://oauth2.googleapis.com/token';

        $response = Http::asForm()->post($tokenEndpoint, [
            'client_id' => config('services.google_mcp.oauth_client_id'),
            'client_secret' => config('services.google_mcp.oauth_client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw $this->errorMapper->fromResponse($response);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $this->tokenStore->mergeTokenResponse($payload);
    }
}
