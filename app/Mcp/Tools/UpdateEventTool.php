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
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateEventTool extends Tool
{
    use InteractsWithGoogleCalendarErrors;
    use InteractsWithTimezoneOffsetValidation;

    protected string $name = 'update_event';

    protected string $description = <<<'MARKDOWN'
        Update an existing timed or all-day Google Calendar event with idempotent retry support.
        For timed updates, use start_at/end_at/timezone.
        For all-day updates, use start_date/end_date and omit timezone.
        Resolve user-local times in the end-user's timezone and pass RFC3339 datetimes with explicit offsets.
        Do not assume UTC unless the user explicitly requested UTC.
    MARKDOWN;

    public function handle(Request $request, GoogleCalendarService $service, IdempotencyStore $idempotencyStore): ResponseFactory
    {
        $validated = $this->validateInput($request);
        /** @var string $idempotencyKey */
        $idempotencyKey = $validated['idempotency_key'];

        try {
            $result = $this->updateIdempotentEvent(
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
            'event_id' => ['required', 'string'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'start_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
                'required_with:end_at',
                'prohibits:start_date,end_date',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $this->validateDatetimeOffsetMatchesTimezone($attribute, $value, $request->get('timezone'), $fail);
                },
            ],
            'end_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
                'required_with:start_at',
                'prohibits:start_date,end_date',
                'after:start_at',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $this->validateDatetimeOffsetMatchesTimezone($attribute, $value, $request->get('timezone'), $fail);
                },
            ],
            'timezone' => [
                'required_with:start_at,end_at',
                'nullable',
                'timezone',
                'prohibits:start_date,end_date',
            ],
            'start_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:end_date',
                'prohibits:start_at,end_at,timezone',
            ],
            'end_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:start_date',
                'prohibits:start_at,end_at,timezone',
                'after:start_date',
            ],
            'send_updates' => ['nullable', 'in:all,externalOnly,none'],
            'if_match_etag' => ['nullable', 'string'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{response: array<string, mixed>, replayed: bool}
     */
    protected function updateIdempotentEvent(
        GoogleCalendarService $service,
        IdempotencyStore $idempotencyStore,
        array $validated,
        string $idempotencyKey,
    ): array {
        return $idempotencyStore->run('update_event', $idempotencyKey, $validated, function () use ($service, $validated): array {
            return $service->updateEvent($validated);
        });
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'calendar_id' => $schema->string()->nullable(),
            'event_id' => $schema->string()->required(),
            'summary' => $schema->string()->nullable()->max(255),
            'description' => $schema->string()->nullable(),
            'location' => $schema->string()->nullable(),
            'start_at' => $schema->string()->nullable()->description('RFC3339 datetime with explicit offset. Offset must match timezone at that instant.'),
            'end_at' => $schema->string()->nullable()->description('RFC3339 datetime with explicit offset. Offset must match timezone at that instant.'),
            'timezone' => $schema->string()->nullable()->description('IANA timezone for end-user local intent (example: Europe/Kyiv). Required for timed updates, omitted for all-day updates.'),
            'start_date' => $schema->string()->nullable()->description('All-day start date in YYYY-MM-DD format. Use with end_date, omit start_at/end_at/timezone.'),
            'end_date' => $schema->string()->nullable()->description('All-day exclusive end date in YYYY-MM-DD format (single-day event: next day).'),
            'send_updates' => $schema->string()->enum(['all', 'externalOnly', 'none'])->nullable(),
            'if_match_etag' => $schema->string()->nullable(),
            'idempotency_key' => $schema->string()->required()->max(120),
        ];
    }
}
