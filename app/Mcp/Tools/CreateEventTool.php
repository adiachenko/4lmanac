<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithGoogleCalendarErrors;
use App\Mcp\Tools\Concerns\InteractsWithTimezoneOffsetValidation;
use App\Services\GoogleCalendar\GoogleCalendarException;
use App\Services\GoogleCalendar\GoogleCalendarService;
use App\Services\GoogleCalendar\IdempotencyStore;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateEventTool extends Tool
{
    use InteractsWithGoogleCalendarErrors;
    use InteractsWithTimezoneOffsetValidation;

    protected string $name = 'create_event';

    protected string $description = <<<'MARKDOWN'
        Create a timed or all-day Google Calendar event with idempotent retry support.
        For timed events, use start_at/end_at/timezone.
        For all-day events, use start_date/end_date and omit timezone.
        Resolve user-local times in the end-user's timezone and pass RFC3339 datetimes with explicit offsets.
        Do not assume UTC unless the user explicitly requested UTC.
    MARKDOWN;

    public function handle(Request $request, GoogleCalendarService $service, IdempotencyStore $idempotencyStore): ResponseFactory
    {
        $validated = $this->validateInput($request);
        /** @var string $idempotencyKey */
        $idempotencyKey = $validated['idempotency_key'];

        try {
            $result = $this->createIdempotentEvent(
                service: $service,
                idempotencyStore: $idempotencyStore,
                validated: $validated,
                idempotencyKey: $idempotencyKey,
            );
        } catch (GoogleCalendarException $exception) {
            return $this->errorResponse($exception);
        }

        return Response::structured([
            'event' => $result['response'],
            'idempotent_replay' => $result['replayed'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateInput(Request $request): array
    {
        return $request->validate([
            'calendar_id' => ['nullable', 'string'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'start_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
                'required_without_all:start_date,end_date',
                'required_with:end_at',
                'prohibits:start_date,end_date',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $this->validateDatetimeOffsetMatchesTimezone($attribute, $value, $request->get('timezone'), $fail);
                },
            ],
            'end_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
                'required_without_all:start_date,end_date',
                'required_with:start_at',
                'prohibits:start_date,end_date',
                'after:start_at',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $this->validateDatetimeOffsetMatchesTimezone($attribute, $value, $request->get('timezone'), $fail);
                },
            ],
            'timezone' => [
                'nullable',
                'timezone',
                'required_with:start_at,end_at',
                'prohibits:start_date,end_date',
            ],
            'start_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_without_all:start_at,end_at',
                'required_with:end_date',
                'prohibits:start_at,end_at,timezone',
            ],
            'end_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_without_all:start_at,end_at',
                'required_with:start_date',
                'prohibits:start_at,end_at,timezone',
                'after:start_date',
            ],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['required', 'email'],
            'send_updates' => ['nullable', 'in:all,externalOnly,none'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{response: array<string, mixed>, replayed: bool}
     */
    protected function createIdempotentEvent(
        GoogleCalendarService $service,
        IdempotencyStore $idempotencyStore,
        array $validated,
        string $idempotencyKey,
    ): array {
        return $idempotencyStore->run('create_event', $idempotencyKey, $validated, function () use ($service, $validated, $idempotencyKey): array {
            $googleEventId = $this->googleEventId($idempotencyKey);

            try {
                return $service->createEvent([
                    ...$validated,
                    'google_event_id' => $googleEventId,
                ]);
            } catch (GoogleCalendarException $exception) {
                if ($exception->errorCode !== 'CONFLICT') {
                    throw $exception;
                }

                return $service->getEvent([
                    'calendar_id' => $validated['calendar_id'] ?? null,
                    'event_id' => $googleEventId,
                ]);
            }
        });
    }

    protected function googleEventId(string $idempotencyKey): string
    {
        $hashPrefix = substr(hash('sha256', $idempotencyKey), 0, 29);

        return Str::lower("mcp{$hashPrefix}");
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'calendar_id' => $schema->string()->nullable(),
            'summary' => $schema->string()->required()->max(255),
            'description' => $schema->string()->nullable(),
            'location' => $schema->string()->nullable(),
            'start_at' => $schema->string()->nullable()->description('RFC3339 datetime with explicit offset. Use with end_at/timezone for timed events.'),
            'end_at' => $schema->string()->nullable()->description('RFC3339 datetime with explicit offset. Use with start_at/timezone for timed events.'),
            'timezone' => $schema->string()->nullable()->description('IANA timezone for end-user local intent (example: Europe/Kyiv). Required for timed events, omitted for all-day events.'),
            'start_date' => $schema->string()->nullable()->description('All-day start date in YYYY-MM-DD format. Use with end_date, omit start_at/end_at/timezone.'),
            'end_date' => $schema->string()->nullable()->description('All-day exclusive end date in YYYY-MM-DD format (single-day event: next day).'),
            'attendees' => $schema->array()->items($schema->string())->nullable(),
            'send_updates' => $schema->string()->enum(['all', 'externalOnly', 'none'])->nullable(),
            'idempotency_key' => $schema->string()->required()->max(120),
        ];
    }
}
