<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\DeleteEventTool;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('deletes an event', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => Http::response('', 204),
    ]);

    $response = GoogleCalendarServer::tool(DeleteEventTool::class, [
        'event_id' => 'evt-delete-1',
        'idempotency_key' => 'idem-delete-1',
    ]);

    $response->assertOk()->assertStructuredContent([
        'deleted' => true,
        'event_id' => 'evt-delete-1',
        'idempotent_replay' => false,
    ]);
});

test('replays delete idempotently without repeated upstream call', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => Http::response('', 204),
    ]);

    GoogleCalendarServer::tool(DeleteEventTool::class, [
        'event_id' => 'evt-delete-2',
        'idempotency_key' => 'idem-delete-2',
    ])->assertOk();

    GoogleCalendarServer::tool(DeleteEventTool::class, [
        'event_id' => 'evt-delete-2',
        'idempotency_key' => 'idem-delete-2',
    ])->assertOk()->assertStructuredContent([
        'deleted' => true,
        'event_id' => 'evt-delete-2',
        'idempotent_replay' => true,
    ]);

    Http::assertSentCount(1);
});
