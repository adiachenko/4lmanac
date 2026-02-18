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

        $this->table(['Field', 'Value'], $this->tableRows($payload));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{string, string}>
     */
    protected function tableRows(array $payload): array
    {
        $expiresAt = $this->expiresAt($payload);
        $tokenFileConfig = config('services.google_mcp.token_file', 'n/a');
        $tokenFile = is_string($tokenFileConfig) ? $tokenFileConfig : 'n/a';

        return [
            ['token_file', $tokenFile],
            ['has_access_token', is_string($payload['access_token'] ?? null) ? 'yes' : 'no'],
            ['has_refresh_token', is_string($payload['refresh_token'] ?? null) ? 'yes' : 'no'],
            ['expires_at', $expiresAt?->toIso8601String() ?? 'n/a'],
            ['expired', $expiresAt !== null && $expiresAt->isPast() ? 'yes' : 'no'],
            ['scope', $this->scope($payload)],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function expiresAt(array $payload): ?CarbonImmutable
    {
        if (! is_string($payload['expires_at'] ?? null)) {
            return null;
        }

        return CarbonImmutable::parse($payload['expires_at']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function scope(array $payload): string
    {
        return is_string($payload['scope'] ?? null) ? $payload['scope'] : 'n/a';
    }
}
