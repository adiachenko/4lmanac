<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Services\GoogleCalendar\GoogleTokenStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class HandleGoogleCalendarBootstrapCallbackController
{
    public function __construct(
        protected GoogleTokenStore $tokenStore,
    ) {}

    public function __invoke(Request $request): \Illuminate\Http\Response
    {
        $state = $request->query('state');
        $code = $request->query('code');

        if (! is_string($state) || ! is_string($code) || $state === '' || $code === '') {
            return response('Missing required OAuth callback parameters.', 422);
        }

        $expectedState = $this->readExpectedState();

        if (! is_string($expectedState) || ! hash_equals($expectedState, $state)) {
            return response('Invalid OAuth state. Start bootstrap again.', 422);
        }

        $tokenEndpoint = config('services.google_mcp.oauth_token_endpoint');
        $tokenEndpoint = is_string($tokenEndpoint) ? $tokenEndpoint : '';

        $response = Http::asForm()->post($tokenEndpoint, [
            'code' => $code,
            'client_id' => config('services.google_mcp.oauth_client_id'),
            'client_secret' => config('services.google_mcp.oauth_client_secret'),
            'redirect_uri' => config('services.google_mcp.oauth_redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            return response('Token exchange failed: '.$response->body(), 422);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        $this->tokenStore->mergeTokenResponse($payload);

        $this->clearState();

        return response('Google Calendar bootstrap completed successfully. Shared tokens were stored.', 200);
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

    protected function stateFilePath(): string
    {
        return storage_path('app/mcp/google-bootstrap-state.json');
    }
}
