<?php

declare(strict_types=1);

namespace App\Console\Commands\GoogleCalendar;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

class BootstrapCommand extends Command
{
    protected $signature = 'app:google-calendar:bootstrap';

    protected $description = 'Generate Google OAuth bootstrap URL for shared calendar token setup.';

    public function handle(): int
    {
        $oauthConfiguration = $this->oauthConfiguration();

        if ($oauthConfiguration === null) {
            return self::FAILURE;
        }

        $state = $this->newState();
        $url = $this->authorizationUrl($oauthConfiguration, $state);

        $this->storeState($state);
        $this->renderBootstrapInstructions($url);

        return self::SUCCESS;
    }

    /**
     * @return array{client_id: string, redirect_uri: string, authorization_endpoint: string}|null
     */
    protected function oauthConfiguration(): ?array
    {
        $clientIdConfig = config('services.google_mcp.oauth_client_id', '');
        $redirectUriConfig = config('services.google_mcp.oauth_redirect_uri', '');

        $clientId = is_string($clientIdConfig) ? $clientIdConfig : '';
        $redirectUri = is_string($redirectUriConfig) ? $redirectUriConfig : '';

        if ($clientId === '' || $redirectUri === '') {
            $this->components->error('Missing GOOGLE_OAUTH_CLIENT_ID or GOOGLE_OAUTH_REDIRECT_URI configuration.');

            return null;
        }

        $authorizationEndpointConfig = config('services.google_mcp.oauth_authorization_endpoint', '');
        $authorizationEndpoint = is_string($authorizationEndpointConfig) ? $authorizationEndpointConfig : '';

        if ($authorizationEndpoint === '') {
            $this->components->error('Missing GOOGLE OAuth authorization endpoint configuration.');

            return null;
        }

        return [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'authorization_endpoint' => $authorizationEndpoint,
        ];
    }

    protected function newState(): string
    {
        return Str::random(48);
    }

    /**
     * @param  array{client_id: string, redirect_uri: string, authorization_endpoint: string}  $oauthConfiguration
     */
    protected function authorizationUrl(array $oauthConfiguration, string $state): string
    {
        return $oauthConfiguration['authorization_endpoint'].'?'.http_build_query([
            'client_id' => $oauthConfiguration['client_id'],
            'redirect_uri' => $oauthConfiguration['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    protected function renderBootstrapInstructions(string $url): void
    {
        $this->components->info('Open this URL in your browser to authorize the shared calendar account:');
        $this->line($url);
        $this->newLine();
        $this->components->info('After consent, Google will redirect to your bootstrap callback route and persist tokens automatically.');
    }

    protected function storeState(string $state): void
    {
        $path = $this->stateFilePath();

        File::ensureDirectoryExists(dirname($path));

        try {
            File::put($path, json_encode([
                'state' => $state,
                'created_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            File::put($path, '{}');
        }
    }

    protected function stateFilePath(): string
    {
        $configuredPath = config('services.google_mcp.bootstrap_state_file', storage_path('app/mcp/google-bootstrap-state.json'));

        return is_string($configuredPath) && trim($configuredPath) !== ''
            ? $configuredPath
            : storage_path('app/mcp/google-bootstrap-state.json');
    }
}
