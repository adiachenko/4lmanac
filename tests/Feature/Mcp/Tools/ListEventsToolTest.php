<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\ListEventsTool;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('lists calendar events', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [
                [
                    'id' => 'evt-1',
                    'summary' => 'Planning',
                    'status' => 'confirmed',
                    'start' => ['dateTime' => '2030-01-01T10:00:00Z'],
                    'end' => ['dateTime' => '2030-01-01T11:00:00Z'],
                ],
            ],
            'nextPageToken' => 'page-2',
        ]),
    ]);

    $response = GoogleCalendarServer::tool(ListEventsTool::class, [
        'time_min' => '2030-01-01T00:00:00+00:00',
        'time_max' => '2030-01-02T00:00:00+00:00',
        'timezone' => 'UTC',
        'max_results' => 10,
    ]);

    $response->assertOk()->assertStructuredContent([
        'events' => [
            [
                'id' => 'evt-1',
                'summary' => 'Planning',
                'status' => 'confirmed',
                'start' => ['dateTime' => '2030-01-01T10:00:00Z'],
                'end' => ['dateTime' => '2030-01-01T11:00:00Z'],
            ],
        ],
        'next_page_token' => 'page-2',
        'timezone' => 'UTC',
    ]);
});

test('validates required date range fields for list events', function (): void {
    $response = GoogleCalendarServer::tool(ListEventsTool::class, [
        'timezone' => 'UTC',
    ]);

    $response->assertHasErrors([
        'time min field is required',
        'time max field is required',
    ]);
});

test('maps google api errors to structured mcp errors', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'error' => [
                'message' => 'Quota exceeded',
                'errors' => [
                    ['reason' => 'quotaExceeded'],
                ],
            ],
        ], 403),
    ]);

    $response = GoogleCalendarServer::tool(ListEventsTool::class, [
        'time_min' => '2030-01-01T00:00:00+00:00',
        'time_max' => '2030-01-02T00:00:00+00:00',
        'timezone' => 'UTC',
    ]);

    $response->assertHasErrors(['Quota exceeded'])->assertStructuredContent([
        'error' => [
            'code' => 'RATE_LIMITED',
            'message' => 'Quota exceeded',
            'http_status' => 403,
            'context' => [
                'google_reason' => 'quotaExceeded',
            ],
        ],
    ]);
});
