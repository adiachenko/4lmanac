<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithGoogleCalendarErrors;
use App\Services\GoogleCalendar\GoogleCalendarException;
use App\Services\GoogleCalendar\GoogleCalendarService;
use App\Services\GoogleCalendar\IdempotencyStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class DeleteEventTool extends Tool
{
    use InteractsWithGoogleCalendarErrors;

    protected string $name = 'delete_event';

    protected string $description = <<<'MARKDOWN'
        Delete an event from Google Calendar with idempotent retry support.
    MARKDOWN;

    public function handle(Request $request, GoogleCalendarService $service, IdempotencyStore $idempotencyStore): ResponseFactory
    {
        $validated = $this->validateInput($request);
        /** @var string $idempotencyKey */
        $idempotencyKey = $validated['idempotency_key'];
        /** @var string $eventId */
        $eventId = $validated['event_id'];

        try {
            $result = $this->deleteIdempotentEvent(
                service: $service,
                idempotencyStore: $idempotencyStore,
                validated: $validated,
                idempotencyKey: $idempotencyKey,
                eventId: $eventId,
            );
        } catch (GoogleCalendarException $exception) {
            return $this->errorResponse($exception);
        }

        return Response::structured([
            'deleted' => (bool) ($result['response']['deleted'] ?? false),
            'event_id' => is_string($result['response']['event_id'] ?? null) ? $result['response']['event_id'] : $eventId,
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
            'if_match_etag' => ['nullable', 'string'],
            'send_updates' => ['nullable', 'in:all,externalOnly,none'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{response: array<string, mixed>, replayed: bool}
     */
    protected function deleteIdempotentEvent(
        GoogleCalendarService $service,
        IdempotencyStore $idempotencyStore,
        array $validated,
        string $idempotencyKey,
        string $eventId,
    ): array {
        return $idempotencyStore->run('delete_event', $idempotencyKey, $validated, function () use ($service, $validated, $eventId): array {
            $service->deleteEvent($validated);

            return [
                'deleted' => true,
                'event_id' => $eventId,
            ];
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
            'if_match_etag' => $schema->string()->nullable(),
            'send_updates' => $schema->string()->enum(['all', 'externalOnly', 'none'])->nullable(),
            'idempotency_key' => $schema->string()->required()->max(120),
        ];
    }
}
