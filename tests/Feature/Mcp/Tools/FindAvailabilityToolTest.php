<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\FindAvailabilityTool;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithGoogleMcpStorage;

uses(InteractsWithGoogleMcpStorage::class);

beforeEach(function (): void {
    $this->configureGoogleMcpStoragePaths();
    $this->writeGoogleAccessToken();
});

test('returns freebusy windows with suggested slots', function (): void {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy*' => Http::response([
            'calendars' => [
                'primary' => [
                    'busy' => [
                        [
                            'start' => '2030-05-01T10:00:00Z',
                            'end' => '2030-05-01T11:00:00Z',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $response = GoogleCalendarServer::tool(FindAvailabilityTool::class, [
        'time_min' => '2030-05-01T09:00:00+00:00',
        'time_max' => '2030-05-01T13:00:00+00:00',
        'timezone' => 'UTC',
        'slot_duration_minutes' => 30,
        'slot_step_minutes' => 30,
    ]);

    $response->assertOk()->assertStructuredContent([
        'busy_by_calendar' => [
            'primary' => [
                [
                    'start' => '2030-05-01T10:00:00Z',
                    'end' => '2030-05-01T11:00:00Z',
                ],
            ],
        ],
        'suggested_slots' => [
            [
                'start' => '2030-05-01T09:00:00+00:00',
                'end' => '2030-05-01T09:30:00+00:00',
            ],
            [
                'start' => '2030-05-01T09:30:00+00:00',
                'end' => '2030-05-01T10:00:00+00:00',
            ],
            [
                'start' => '2030-05-01T11:00:00+00:00',
                'end' => '2030-05-01T11:30:00+00:00',
            ],
            [
                'start' => '2030-05-01T11:30:00+00:00',
                'end' => '2030-05-01T12:00:00+00:00',
            ],
            [
                'start' => '2030-05-01T12:00:00+00:00',
                'end' => '2030-05-01T12:30:00+00:00',
            ],
            [
                'start' => '2030-05-01T12:30:00+00:00',
                'end' => '2030-05-01T13:00:00+00:00',
            ],
        ],
        'timezone' => 'UTC',
    ]);
});
