<?php

declare(strict_types=1);

use App\Services\GoogleCalendar\GoogleCalendarService;
use App\Services\GoogleCalendar\GoogleTokenRefresher;
use App\Services\GoogleCalendar\GoogleTokenStore;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if ((string) config('services.google_mcp.token_file', '') === '' || ! file_exists((string) config('services.google_mcp.token_file'))) {
        $this->markTestSkipped('Bootstrap token file is missing. Run bootstrap flow first.');
    }

    $externalCalendarId = config('services.google_mcp.external_test_calendar_id');

    if (! is_string($externalCalendarId) || trim($externalCalendarId) === '') {
        $this->markTestSkipped('Set GOOGLE_EXTERNAL_TEST_CALENDAR_ID to run external Google API tests safely.');
    }
});

test('lists events using google api', function (): void {
    $service = resolve(GoogleCalendarService::class);

    $response = $service->listEvents([
        'calendar_id' => config('services.google_mcp.external_test_calendar_id'),
        'time_min' => '2030-01-01T00:00:00+00:00',
        'time_max' => '2031-01-01T00:00:00+00:00',
        'timezone' => 'UTC',
        'max_results' => 10,
    ]);

    expect($response)->toHaveKey('items');
});

test('refreshes google oauth access token', function (): void {
    $tokenStore = resolve(GoogleTokenStore::class);
    $refreshToken = $tokenStore->refreshToken();

    if (! is_string($refreshToken) || $refreshToken === '') {
        $this->markTestSkipped('No refresh token found in bootstrap token file.');
    }

    $refreshed = resolve(GoogleTokenRefresher::class)->refreshAccessToken();

    expect($refreshed)->toHaveKey('access_token');
});

test('searches events using google api', function (): void {
    $service = resolve(GoogleCalendarService::class);

    $response = $service->searchEvents([
        'calendar_id' => config('services.google_mcp.external_test_calendar_id'),
        'query' => 'MCP_TMP_',
        'time_min' => '2030-01-01T00:00:00+00:00',
        'time_max' => '2031-01-01T00:00:00+00:00',
        'timezone' => 'UTC',
    ]);

    expect($response)->toHaveKey('items');
});

test('creates updates and deletes event using google api', function (): void {
    $service = resolve(GoogleCalendarService::class);

    $calendarId = (string) config('services.google_mcp.external_test_calendar_id');
    $suffix = Str::lower(Str::random(8));

    $created = $service->createEvent([
        'calendar_id' => $calendarId,
        'summary' => "MCP_TMP_CREATE_{$suffix}",
        'description' => 'External integration test event',
        'start_at' => '2030-07-01T10:00:00+00:00',
        'end_at' => '2030-07-01T11:00:00+00:00',
        'timezone' => 'UTC',
        'send_updates' => 'none',
    ]);

    expect($created)->toHaveKey('id');

    $eventId = (string) $created['id'];

    try {
        $updated = $service->updateEvent([
            'calendar_id' => $calendarId,
            'event_id' => $eventId,
            'summary' => "MCP_TMP_UPDATED_{$suffix}",
            'start_at' => '2030-07-01T10:30:00+00:00',
            'end_at' => '2030-07-01T11:30:00+00:00',
            'timezone' => 'UTC',
            'send_updates' => 'none',
        ]);

        expect($updated)->toHaveKey('id', $eventId);
    } finally {
        $service->deleteEvent([
            'calendar_id' => $calendarId,
            'event_id' => $eventId,
            'send_updates' => 'none',
        ]);
    }
});

test('queries freebusy using google api', function (): void {
    $service = resolve(GoogleCalendarService::class);

    $response = $service->freeBusy([
        'time_min' => '2030-08-01T00:00:00+00:00',
        'time_max' => '2030-08-02T00:00:00+00:00',
        'timezone' => 'UTC',
        'calendar_ids' => [(string) config('services.google_mcp.external_test_calendar_id')],
    ]);

    expect($response)->toHaveKey('calendars');
});

test('creates updates and deletes all day event using google api', function (): void {
    $service = resolve(GoogleCalendarService::class);

    $calendarId = (string) config('services.google_mcp.external_test_calendar_id');
    $suffix = Str::lower(Str::random(8));

    $created = $service->createEvent([
        'calendar_id' => $calendarId,
        'summary' => "MCP_TMP_ALL_DAY_CREATE_{$suffix}",
        'description' => 'External integration test all day event',
        'start_date' => '2030-09-15',
        'end_date' => '2030-09-16',
        'send_updates' => 'none',
    ]);

    expect($created)->toHaveKey('id');

    $eventId = (string) $created['id'];

    try {
        $updated = $service->updateEvent([
            'calendar_id' => $calendarId,
            'event_id' => $eventId,
            'summary' => "MCP_TMP_ALL_DAY_UPDATED_{$suffix}",
            'start_date' => '2030-09-16',
            'end_date' => '2030-09-17',
            'send_updates' => 'none',
        ]);

        expect($updated)->toHaveKey('id', $eventId);
        expect($updated)->toHaveKey('start.date');
        expect($updated)->toHaveKey('end.date');
    } finally {
        $service->deleteEvent([
            'calendar_id' => $calendarId,
            'event_id' => $eventId,
            'send_updates' => 'none',
        ]);
    }
});
