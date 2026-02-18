<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\UpdateEventTool;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('updates a timed event', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => Http::response([
            'id' => 'evt-update-1',
            'summary' => 'Updated event',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2030-04-01T11:00:00+00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2030-04-01T12:00:00+00:00', 'timeZone' => 'UTC'],
        ], 200),
    ]);

    $response = GoogleCalendarServer::tool(UpdateEventTool::class, [
        'event_id' => 'evt-update-1',
        'summary' => 'Updated event',
        'start_at' => '2030-04-01T11:00:00+00:00',
        'end_at' => '2030-04-01T12:00:00+00:00',
        'timezone' => 'UTC',
        'idempotency_key' => 'idem-update-1',
    ]);

    $response->assertOk()->assertStructuredContent([
        'event' => [
            'id' => 'evt-update-1',
            'summary' => 'Updated event',
            'status' => 'confirmed',
            'start' => ['dateTime' => '2030-04-01T11:00:00+00:00', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2030-04-01T12:00:00+00:00', 'timeZone' => 'UTC'],
        ],
        'idempotent_replay' => false,
    ]);
});

test('enforces explicit timezone offset and chronological range for update', function (): void {
    $response = GoogleCalendarServer::tool(UpdateEventTool::class, [
        'event_id' => 'evt-update-2',
        'start_at' => '2030-04-01T12:00:00+00:00',
        'end_at' => '2030-04-01T11:00:00+00:00',
        'timezone' => 'UTC',
        'idempotency_key' => 'idem-update-2',
    ]);

    $response->assertHasErrors(['end at field must be a date after start at']);
});

test('rejects update event payload when datetime offset does not match timezone', function (): void {
    $response = GoogleCalendarServer::tool(UpdateEventTool::class, [
        'event_id' => 'evt-update-3',
        'start_at' => '2026-02-18T16:00:00+00:00',
        'end_at' => '2026-02-18T17:00:00+00:00',
        'timezone' => 'Europe/Kyiv',
        'idempotency_key' => 'idem-update-offset-mismatch',
    ]);

    $response->assertHasErrors([
        'start at offset must match the provided timezone at that datetime',
        'end at offset must match the provided timezone at that datetime',
    ]);
});

test('updates an all day event', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => Http::response([
            'id' => 'evt-update-all-day-1',
            'summary' => 'Updated all day event',
            'status' => 'confirmed',
            'start' => ['date' => '2030-04-10'],
            'end' => ['date' => '2030-04-11'],
        ], 200),
    ]);

    $response = GoogleCalendarServer::tool(UpdateEventTool::class, [
        'event_id' => 'evt-update-all-day-1',
        'summary' => 'Updated all day event',
        'start_date' => '2030-04-10',
        'end_date' => '2030-04-11',
        'idempotency_key' => 'idem-update-all-day-1',
    ]);

    $response->assertOk()->assertStructuredContent([
        'event' => [
            'id' => 'evt-update-all-day-1',
            'summary' => 'Updated all day event',
            'status' => 'confirmed',
            'start' => ['date' => '2030-04-10'],
            'end' => ['date' => '2030-04-11'],
        ],
        'idempotent_replay' => false,
    ]);
});

test('rejects mixed timed and all day fields for update event', function (): void {
    $response = GoogleCalendarServer::tool(UpdateEventTool::class, [
        'event_id' => 'evt-update-mixed-1',
        'start_at' => '2030-04-01T11:00:00+00:00',
        'end_at' => '2030-04-01T12:00:00+00:00',
        'timezone' => 'UTC',
        'start_date' => '2030-04-01',
        'end_date' => '2030-04-02',
        'idempotency_key' => 'idem-update-mixed-mode',
    ]);

    $response->assertHasErrors([
        'start at field prohibits',
        'end at field prohibits',
        'timezone field prohibits',
        'start date field prohibits',
        'end date field prohibits',
    ]);
});
