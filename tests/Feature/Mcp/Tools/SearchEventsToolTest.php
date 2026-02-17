<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\SearchEventsTool;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('searches events using free text query', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [
                [
                    'id' => 'evt-search-1',
                    'summary' => 'Launch Sync',
                    'status' => 'confirmed',
                    'start' => ['dateTime' => '2030-02-01T09:00:00Z'],
                    'end' => ['dateTime' => '2030-02-01T10:00:00Z'],
                ],
            ],
        ]),
    ]);

    $response = GoogleCalendarServer::tool(SearchEventsTool::class, [
        'query' => 'Launch',
        'time_min' => '2030-02-01T00:00:00+00:00',
        'time_max' => '2030-02-02T00:00:00+00:00',
        'timezone' => 'UTC',
    ]);

    $response->assertOk()->assertStructuredContent([
        'events' => [
            [
                'id' => 'evt-search-1',
                'summary' => 'Launch Sync',
                'status' => 'confirmed',
                'start' => ['dateTime' => '2030-02-01T09:00:00Z'],
                'end' => ['dateTime' => '2030-02-01T10:00:00Z'],
            ],
        ],
        'next_page_token' => null,
        'timezone' => 'UTC',
    ]);
});

test('requires a search query', function (): void {
    $response = GoogleCalendarServer::tool(SearchEventsTool::class, [
        'time_min' => '2030-02-01T00:00:00+00:00',
        'time_max' => '2030-02-02T00:00:00+00:00',
        'timezone' => 'UTC',
    ]);

    $response->assertHasErrors(['query field is required']);
});
