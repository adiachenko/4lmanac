<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    protected string $baseUrl;

    public function __construct(
        protected GoogleTokenStore $tokenStore,
        protected GoogleTokenRefresher $tokenRefresher,
        protected GoogleCalendarErrorMapper $errorMapper,
    ) {
        $configuredBaseUrl = config('services.google_mcp.calendar_api_base_url', 'https://www.googleapis.com/calendar/v3');
        $this->baseUrl = is_string($configuredBaseUrl) ? $configuredBaseUrl : 'https://www.googleapis.com/calendar/v3';
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $json
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $uri,
        array $query = [],
        array $json = [],
        array $headers = [],
    ): array {
        $token = $this->resolveAccessToken();

        $response = $this->send($method, $uri, $token, $query, $json, $headers);

        if ($response->status() === 401) {
            $this->tokenRefresher->refreshAccessToken();

            $token = $this->resolveAccessToken();
            $response = $this->send($method, $uri, $token, $query, $json, $headers);
        }

        if (! $response->successful()) {
            throw $this->errorMapper->fromResponse($response);
        }

        if ($response->status() === 204) {
            return [];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    protected function resolveAccessToken(): string
    {
        $accessToken = $this->tokenStore->currentAccessToken();

        if (is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        $payload = $this->tokenRefresher->refreshAccessToken();
        $refreshedToken = $payload['access_token'] ?? null;

        if (! is_string($refreshedToken) || $refreshedToken === '') {
            throw new GoogleCalendarException('GOOGLE_REAUTH_REQUIRED', 401, 'Google access token could not be refreshed.');
        }

        return $refreshedToken;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $json
     * @param  array<string, string>  $headers
     */
    protected function send(
        string $method,
        string $uri,
        string $token,
        array $query,
        array $json,
        array $headers,
    ): \Illuminate\Http\Client\Response {
        $request = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->withToken($token)
            ->withHeaders($headers);

        $options = [
            'query' => $query,
        ];

        if ($json !== []) {
            $options['json'] = $json;
        }

        return $request->send($method, ltrim($uri, '/'), $options);
    }
}
