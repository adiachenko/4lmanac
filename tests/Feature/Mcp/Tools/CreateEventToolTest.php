<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\CreateEventTool;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('creates a timed event', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'id' => 'evt-create-1',
            'summary' => 'Created event',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2030-03-01T11:00:00+00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2030-03-01T12:00:00+00:00', 'timeZone' => 'UTC'],
        ], 200),
    ]);

    $response = GoogleCalendarServer::tool(CreateEventTool::class, [
        'summary' => 'Created event',
        'start_at' => '2030-03-01T11:00:00+00:00',
        'end_at' => '2030-03-01T12:00:00+00:00',
        'timezone' => 'UTC',
        'idempotency_key' => 'idem-create-1',
    ]);

    $response->assertOk()->assertStructuredContent([
        'event' => [
            'id' => 'evt-create-1',
            'summary' => 'Created event',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2030-03-01T11:00:00+00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2030-03-01T12:00:00+00:00', 'timeZone' => 'UTC'],
        ],
        'idempotent_replay' => false,
    ]);
});

test('replays create event idempotently on duplicate request', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'id' => 'evt-create-2',
            'summary' => 'Idempotent event',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2030-03-02T11:00:00+00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2030-03-02T12:00:00+00:00', 'timeZone' => 'UTC'],
        ], 200),
    ]);

    GoogleCalendarServer::tool(CreateEventTool::class, [
        'summary' => 'Idempotent event',
        'start_at' => '2030-03-02T11:00:00+00:00',
        'end_at' => '2030-03-02T12:00:00+00:00',
        'timezone' => 'UTC',
        'idempotency_key' => 'idem-create-2',
    ])->assertOk();

    GoogleCalendarServer::tool(CreateEventTool::class, [
        'summary' => 'Idempotent event',
        'start_at' => '2030-03-02T11:00:00+00:00',
        'end_at' => '2030-03-02T12:00:00+00:00',
        'timezone' => 'UTC',
        'idempotency_key' => 'idem-create-2',
    ])->assertOk()->assertStructuredContent([
        'event' => [
            'id' => 'evt-create-2',
            'summary' => 'Idempotent event',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2030-03-02T11:00:00+00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2030-03-02T12:00:00+00:00', 'timeZone' => 'UTC'],
        ],
        'idempotent_replay' => true,
    ]);

    Http::assertSentCount(1);
});

test('rejects create event payload when datetime offset does not match timezone', function (): void {
    $response = GoogleCalendarServer::tool(CreateEventTool::class, [
        'summary' => 'Timezone mismatch',
        'start_at' => '2026-02-18T16:00:00+00:00',
        'end_at' => '2026-02-18T17:00:00+00:00',
        'timezone' => 'Europe/Kyiv',
        'idempotency_key' => 'idem-create-offset-mismatch',
    ]);

    $response->assertHasErrors([
        'start at offset must match the provided timezone at that datetime',
        'end at offset must match the provided timezone at that datetime',
    ]);
});

test('creates an all day event', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'id' => 'evt-create-all-day-1',
            'summary' => 'All day event',
            'status' => 'confirmed',
            'start' => ['date' => '2030-03-05'],
            'end' => ['date' => '2030-03-06'],
        ], 200),
    ]);

    $response = GoogleCalendarServer::tool(CreateEventTool::class, [
        'summary' => 'All day event',
        'start_date' => '2030-03-05',
        'end_date' => '2030-03-06',
        'idempotency_key' => 'idem-create-all-day-1',
    ]);

    $response->assertOk()->assertStructuredContent([
        'event' => [
            'id' => 'evt-create-all-day-1',
            'summary' => 'All day event',
            'status' => 'confirmed',
            'start' => ['date' => '2030-03-05'],
            'end' => ['date' => '2030-03-06'],
        ],
        'idempotent_replay' => false,
    ]);
});

test('rejects mixed timed and all day fields for create event', function (): void {
    $response = GoogleCalendarServer::tool(CreateEventTool::class, [
        'summary' => 'Mixed mode',
        'start_at' => '2030-03-01T11:00:00+00:00',
        'end_at' => '2030-03-01T12:00:00+00:00',
        'timezone' => 'UTC',
        'start_date' => '2030-03-01',
        'end_date' => '2030-03-02',
        'idempotency_key' => 'idem-create-mixed-mode',
    ]);

    $response->assertHasErrors([
        'start at field prohibits',
        'end at field prohibits',
        'timezone field prohibits',
        'start date field prohibits',
        'end date field prohibits',
    ]);
});
