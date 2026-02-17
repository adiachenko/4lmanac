<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

class GoogleCalendarService
{
    public function __construct(
        protected GoogleCalendarClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function listEvents(array $input): array
    {
        $query = [
            'timeMin' => $input['time_min'],
            'timeMax' => $input['time_max'],
            'timeZone' => $input['timezone'],
            'singleEvents' => $this->googleBoolean(true),
            'orderBy' => 'startTime',
            'maxResults' => $input['max_results'] ?? 50,
            'showDeleted' => $this->googleBoolean($input['include_deleted'] ?? false),
            'pageToken' => $input['page_token'] ?? null,
        ];

        return $this->client->request(
            method: 'GET',
            uri: sprintf('/calendars/%s/events', urlencode($this->calendarId($input))),
            query: array_filter($query, static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function searchEvents(array $input): array
    {
        $query = [
            'q' => $input['query'],
            'timeMin' => $input['time_min'],
            'timeMax' => $input['time_max'],
            'timeZone' => $input['timezone'],
            'singleEvents' => $this->googleBoolean(true),
            'orderBy' => 'startTime',
            'maxResults' => $input['max_results'] ?? 50,
            'pageToken' => $input['page_token'] ?? null,
        ];

        return $this->client->request(
            method: 'GET',
            uri: sprintf('/calendars/%s/events', urlencode($this->calendarId($input))),
            query: array_filter($query, static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createEvent(array $input): array
    {
        $attendees = null;

        if (isset($input['attendees']) && is_array($input['attendees'])) {
            $attendees = [];

            foreach ($input['attendees'] as $attendee) {
                if (! is_string($attendee)) {
                    continue;
                }

                $attendees[] = ['email' => $attendee];
            }
        }

        $event = [
            'id' => $input['google_event_id'] ?? null,
            'summary' => $input['summary'],
            'description' => $input['description'] ?? null,
            'location' => $input['location'] ?? null,
            'start' => $this->buildEventStartPayload($input),
            'end' => $this->buildEventEndPayload($input),
            'attendees' => $attendees,
        ];

        return $this->client->request(
            method: 'POST',
            uri: sprintf('/calendars/%s/events', urlencode($this->calendarId($input))),
            query: [
                'sendUpdates' => $input['send_updates'] ?? 'none',
            ],
            json: array_filter($event, static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function updateEvent(array $input): array
    {
        $eventId = $this->requiredString($input, 'event_id');

        $event = array_filter([
            'summary' => $input['summary'] ?? null,
            'description' => $input['description'] ?? null,
            'location' => $input['location'] ?? null,
            'start' => isset($input['start_date'])
                ? ['date' => $input['start_date']]
                : (isset($input['start_at'])
                    ? [
                        'dateTime' => $input['start_at'],
                        'timeZone' => $input['timezone'],
                    ]
                    : null),
            'end' => isset($input['end_date'])
                ? ['date' => $input['end_date']]
                : (isset($input['end_at'])
                    ? [
                        'dateTime' => $input['end_at'],
                        'timeZone' => $input['timezone'],
                    ]
                    : null),
        ], static fn (mixed $value): bool => $value !== null);

        return $this->client->request(
            method: 'PATCH',
            uri: sprintf('/calendars/%s/events/%s', urlencode($this->calendarId($input)), urlencode($eventId)),
            query: [
                'sendUpdates' => $input['send_updates'] ?? 'none',
            ],
            json: $event,
            headers: array_filter([
                'If-Match' => is_string($input['if_match_etag'] ?? null) ? $input['if_match_etag'] : null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteEvent(array $input): void
    {
        $eventId = $this->requiredString($input, 'event_id');

        $this->client->request(
            method: 'DELETE',
            uri: sprintf('/calendars/%s/events/%s', urlencode($this->calendarId($input)), urlencode($eventId)),
            query: [
                'sendUpdates' => $input['send_updates'] ?? 'none',
            ],
            headers: array_filter([
                'If-Match' => is_string($input['if_match_etag'] ?? null) ? $input['if_match_etag'] : null,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function getEvent(array $input): array
    {
        $eventId = $this->requiredString($input, 'event_id');

        return $this->client->request(
            method: 'GET',
            uri: sprintf('/calendars/%s/events/%s', urlencode($this->calendarId($input)), urlencode($eventId)),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function freeBusy(array $input): array
    {
        /** @var array<int, string> $calendarIds */
        $calendarIds = isset($input['calendar_ids']) && is_array($input['calendar_ids']) && $input['calendar_ids'] !== []
            ? array_values(array_filter($input['calendar_ids'], static fn (mixed $value): bool => is_string($value) && $value !== ''))
            : [$this->calendarId($input)];

        return $this->client->request(
            method: 'POST',
            uri: '/freeBusy',
            json: [
                'timeMin' => $input['time_min'],
                'timeMax' => $input['time_max'],
                'timeZone' => $input['timezone'],
                'items' => array_map(static fn (string $calendarId): array => ['id' => $calendarId], $calendarIds),
                'calendarExpansionMax' => 50,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function calendarId(array $input): string
    {
        if (is_string($input['calendar_id'] ?? null)) {
            return $input['calendar_id'];
        }

        $defaultCalendarId = config('services.google_mcp.calendar_default_id', 'primary');

        return is_string($defaultCalendarId) ? $defaultCalendarId : 'primary';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function requiredString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    protected function buildEventStartPayload(array $input): array
    {
        if (is_string($input['start_date'] ?? null)) {
            return ['date' => $input['start_date']];
        }

        return [
            'dateTime' => $this->requiredString($input, 'start_at'),
            'timeZone' => $this->requiredString($input, 'timezone'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    protected function buildEventEndPayload(array $input): array
    {
        if (is_string($input['end_date'] ?? null)) {
            return ['date' => $input['end_date']];
        }

        return [
            'dateTime' => $this->requiredString($input, 'end_at'),
            'timeZone' => $this->requiredString($input, 'timezone'),
        ];
    }

    protected function googleBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true)
                ? 'true'
                : 'false';
        }

        if (is_numeric($value)) {
            return (int) $value === 1 ? 'true' : 'false';
        }

        return 'false';
    }
}
