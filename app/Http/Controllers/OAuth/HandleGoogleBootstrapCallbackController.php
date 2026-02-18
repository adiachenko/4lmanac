<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Services\GoogleCalendar\GoogleTokenStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class HandleGoogleBootstrapCallbackController
{
    public function __construct(
        protected GoogleTokenStore $tokenStore,
    ) {}

    public function __invoke(Request $request): Response
    {
        $callback = $this->readCallbackParameters($request);

        if ($callback === null) {
            return response('Missing required OAuth callback parameters.', 422);
        }

        if (! $this->hasMatchingState($callback['state'])) {
            return response('Invalid OAuth state. Start bootstrap again.', 422);
        }

        $tokenEndpointConfig = config('services.google_mcp.oauth_token_endpoint', '');
        $tokenEndpoint = is_string($tokenEndpointConfig) ? $tokenEndpointConfig : '';

        if ($tokenEndpoint === '') {
            return response('Missing OAuth token endpoint configuration.', 422);
        }

        $response = Http::asForm()->post($tokenEndpoint, $this->tokenExchangePayload($callback['code']));

        if (! $response->successful()) {
            return response('Token exchange failed: '.$response->body(), 422);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        $this->tokenStore->mergeTokenResponse($payload);

        $this->clearState();

        return response('Google Calendar bootstrap completed successfully. Shared tokens were stored.', 200);
    }

    /**
     * @return array{state: string, code: string}|null
     */
    protected function readCallbackParameters(Request $request): ?array
    {
        $state = $request->query('state');
        $code = $request->query('code');

        if (! is_string($state) || ! is_string($code) || $state === '' || $code === '') {
            return null;
        }

        return [
            'state' => $state,
            'code' => $code,
        ];
    }

    protected function hasMatchingState(string $state): bool
    {
        $expectedState = $this->readExpectedState();

        return is_string($expectedState) && hash_equals($expectedState, $state);
    }

    protected function readExpectedState(): ?string
    {
        $path = $this->stateFilePath();

        if (! File::exists($path)) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode((string) File::get($path), true);

        return is_string($payload['state'] ?? null)
            ? $payload['state']
            : null;
    }

    protected function clearState(): void
    {
        File::delete($this->stateFilePath());
    }

    /**
     * @return array{code: string, client_id: mixed, client_secret: mixed, redirect_uri: mixed, grant_type: string}
     */
    protected function tokenExchangePayload(string $code): array
    {
        return [
            'code' => $code,
            'client_id' => config('services.google_mcp.oauth_client_id'),
            'client_secret' => config('services.google_mcp.oauth_client_secret'),
            'redirect_uri' => config('services.google_mcp.oauth_redirect_uri'),
            'grant_type' => 'authorization_code',
        ];
    }

    protected function stateFilePath(): string
    {
        return storage_path('app/mcp/google-bootstrap-state.json');
    }
}
