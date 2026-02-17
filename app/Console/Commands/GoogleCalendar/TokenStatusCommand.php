<?php

declare(strict_types=1);

namespace App\Console\Commands\GoogleCalendar;

use App\Services\GoogleCalendar\GoogleTokenStore;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class TokenStatusCommand extends Command
{
    protected $signature = 'app:google-calendar:token:status';

    protected $description = 'Show current shared Google calendar token status.';

    public function __construct(
        protected GoogleTokenStore $tokenStore,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $payload = $this->tokenStore->read();

        if ($payload === []) {
            $this->components->warn('No Google token is stored yet. Run app:google-calendar:bootstrap first.');

            return self::SUCCESS;
        }

        $expiresAt = is_string($payload['expires_at'] ?? null)
            ? CarbonImmutable::parse($payload['expires_at'])
            : null;
        $tokenFile = config('services.google_mcp.token_file');
        $tokenFile = is_string($tokenFile) ? $tokenFile : 'n/a';
        $scope = is_string($payload['scope'] ?? null) ? $payload['scope'] : 'n/a';

        $this->table(['Field', 'Value'], [
            ['token_file', $tokenFile],
            ['has_access_token', is_string($payload['access_token'] ?? null) ? 'yes' : 'no'],
            ['has_refresh_token', is_string($payload['refresh_token'] ?? null) ? 'yes' : 'no'],
            ['expires_at', $expiresAt?->toIso8601String() ?? 'n/a'],
            ['expired', $expiresAt !== null && $expiresAt->isPast() ? 'yes' : 'no'],
            ['scope', $scope],
        ]);

        return self::SUCCESS;
    }
}
