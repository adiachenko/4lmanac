<?php

declare(strict_types=1);

use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('sends google boolean query parameters as true false strings when listing events', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [],
        ], 200),
    ]);

    resolve(GoogleCalendarService::class)->listEvents([
        'calendar_id' => 'primary',
        'time_min' => '2030-01-01T00:00:00+00:00',
        'time_max' => '2030-01-02T00:00:00+00:00',
        'timezone' => 'UTC',
        'include_deleted' => false,
    ]);

    Http::assertSent(function (HttpRequest $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['singleEvents'] ?? null) === 'true'
            && ($query['showDeleted'] ?? null) === 'false';
    });
});

test('sends google boolean query parameters as true false strings when searching events', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [],
        ], 200),
    ]);

    resolve(GoogleCalendarService::class)->searchEvents([
        'calendar_id' => 'primary',
        'query' => 'MCP_TMP',
        'time_min' => '2030-01-01T00:00:00+00:00',
        'time_max' => '2030-01-02T00:00:00+00:00',
        'timezone' => 'UTC',
    ]);

    Http::assertSent(function (HttpRequest $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['singleEvents'] ?? null) === 'true';
    });
});
