<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithGoogleCalendarErrors;
use App\Services\GoogleCalendar\GoogleCalendarException;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class FindAvailabilityTool extends Tool
{
    use InteractsWithGoogleCalendarErrors;

    protected string $name = 'find_availability';

    protected string $description = <<<'MARKDOWN'
        Find busy windows and suggest open slots for scheduling in a required time range.
        Resolve ambiguous local times in the end-user timezone and do not assume UTC unless explicitly requested.
    MARKDOWN;

    public function handle(Request $request, GoogleCalendarService $service): ResponseFactory
    {
        $validated = $request->validate([
            'calendar_id' => ['nullable', 'string'],
            'calendar_ids' => ['nullable', 'array'],
            'calendar_ids.*' => ['required', 'string'],
            'time_min' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'time_max' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:time_min'],
            'timezone' => ['required', 'timezone'],
            'slot_duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'slot_step_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'max_slots' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        try {
            $freeBusy = $service->freeBusy($validated);
        } catch (GoogleCalendarException $exception) {
            return $this->errorResponse($exception);
        }

        /** @var array<string, array<string, mixed>> $calendarPayload */
        $calendarPayload = is_array($freeBusy['calendars'] ?? null)
            ? $freeBusy['calendars']
            : [];

        /** @var array<string, array<int, array{start: string, end: string}>> $busyByCalendar */
        $busyByCalendar = [];
        /** @var array<int, array{start: string, end: string}> $busyRanges */
        $busyRanges = [];

        foreach ($calendarPayload as $calendarId => $calendarInfo) {
            $busy = $this->normalizeBusyRanges($calendarInfo['busy'] ?? []);

            $busyByCalendar[$calendarId] = $busy;
            $busyRanges = array_merge($busyRanges, $busy);
        }

        $timeMin = $this->requiredString($validated, 'time_min');
        $timeMax = $this->requiredString($validated, 'time_max');
        $slotDurationMinutes = $this->requiredInt($validated, 'slot_duration_minutes');
        $slotStepMinutes = $this->optionalInt($validated, 'slot_step_minutes', $slotDurationMinutes);
        $maxSlots = $this->optionalInt($validated, 'max_slots', 50);

        $slots = $this->suggestedSlots(
            timeMin: CarbonImmutable::parse($timeMin),
            timeMax: CarbonImmutable::parse($timeMax),
            busyRanges: $busyRanges,
            durationMinutes: $slotDurationMinutes,
            stepMinutes: $slotStepMinutes,
            maxSlots: $maxSlots,
        );

        return Response::structured([
            'busy_by_calendar' => $busyByCalendar,
            'suggested_slots' => $slots,
            'timezone' => $validated['timezone'],
        ]);
    }

    /**
     * @param  array<int, array{start: string, end: string}>  $busyRanges
     * @return array<int, array{start: string, end: string}>
     */
    protected function suggestedSlots(
        CarbonImmutable $timeMin,
        CarbonImmutable $timeMax,
        array $busyRanges,
        int $durationMinutes,
        int $stepMinutes,
        int $maxSlots,
    ): array {
        $slots = [];
        $cursor = $timeMin;

        while ($cursor->lessThan($timeMax) && count($slots) < $maxSlots) {
            $slotEnd = $cursor->addMinutes($durationMinutes);

            if ($slotEnd->greaterThan($timeMax)) {
                break;
            }

            if (! $this->overlapsBusyRanges($cursor, $slotEnd, $busyRanges)) {
                $slots[] = [
                    'start' => $cursor->toIso8601String(),
                    'end' => $slotEnd->toIso8601String(),
                ];
            }

            $cursor = $cursor->addMinutes($stepMinutes);
        }

        return $slots;
    }

    /**
     * @param  array<int, array{start: string, end: string}>  $busyRanges
     */
    protected function overlapsBusyRanges(CarbonImmutable $slotStart, CarbonImmutable $slotEnd, array $busyRanges): bool
    {
        foreach ($busyRanges as $range) {
            $busyStart = CarbonImmutable::parse($range['start']);
            $busyEnd = CarbonImmutable::parse($range['end']);

            if ($slotStart->lessThan($busyEnd) && $slotEnd->greaterThan($busyStart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    protected function normalizeBusyRanges(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $start = $item['start'] ?? null;
            $end = $item['end'] ?? null;

            if (! is_string($start) || ! is_string($end)) {
                continue;
            }

            $normalized[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function requiredInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function optionalInt(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'calendar_id' => $schema->string()->nullable(),
            'calendar_ids' => $schema->array()->items($schema->string())->nullable(),
            'time_min' => $schema->string()->required()->description('RFC3339 datetime with explicit offset. Use end-user local timezone intent.'),
            'time_max' => $schema->string()->required()->description('RFC3339 datetime with explicit offset. Use end-user local timezone intent.'),
            'timezone' => $schema->string()->required()->description('IANA timezone for end-user local intent (example: Europe/Kyiv). Do not assume UTC unless user explicitly requested UTC.'),
            'slot_duration_minutes' => $schema->integer()->required()->min(5)->max(240),
            'slot_step_minutes' => $schema->integer()->nullable()->min(5)->max(240),
            'max_slots' => $schema->integer()->nullable()->min(1)->max(200),
        ];
    }
}
