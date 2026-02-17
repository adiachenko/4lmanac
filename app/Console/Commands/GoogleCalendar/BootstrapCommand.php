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
        $clientId = config('services.google_mcp.oauth_client_id');
        $redirectUri = config('services.google_mcp.oauth_redirect_uri');

        $clientId = is_string($clientId) ? $clientId : '';
        $redirectUri = is_string($redirectUri) ? $redirectUri : '';

        if ($clientId === '' || $redirectUri === '') {
            $this->components->error('Missing GOOGLE_OAUTH_CLIENT_ID or GOOGLE_OAUTH_REDIRECT_URI configuration.');

            return self::FAILURE;
        }

        $state = Str::random(48);

        $this->storeState($state);

        $authorizationEndpoint = config('services.google_mcp.oauth_authorization_endpoint');
        $authorizationEndpoint = is_string($authorizationEndpoint) ? $authorizationEndpoint : '';

        $url = $authorizationEndpoint.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        $this->components->info('Open this URL in your browser to authorize the shared calendar account:');
        $this->line($url);
        $this->newLine();
        $this->components->info('After consent, Google will redirect to your bootstrap callback route and persist tokens automatically.');

        return self::SUCCESS;
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
        return storage_path('app/mcp/google-bootstrap-state.json');
    }
}
