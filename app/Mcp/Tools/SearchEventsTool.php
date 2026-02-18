<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithGoogleCalendarErrors;
use App\Services\GoogleCalendar\GoogleCalendarException;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Override;

class SearchEventsTool extends Tool
{
    use InteractsWithGoogleCalendarErrors;

    protected string $name = 'search_events';

    protected string $description = <<<'MARKDOWN'
        Search events by free-text query within a required time window.
        Resolve ambiguous local times in the end-user timezone and do not assume UTC unless explicitly requested.
    MARKDOWN;

    public function handle(Request $request, GoogleCalendarService $service): ResponseFactory
    {
        $validated = $this->validateInput($request);

        try {
            $result = $service->searchEvents($validated);
        } catch (GoogleCalendarException $exception) {
            return $this->errorResponse($exception);
        }

        return Response::structured([
            'events' => $result['items'] ?? [],
            'next_page_token' => $result['nextPageToken'] ?? null,
            'timezone' => $validated['timezone'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateInput(Request $request): array
    {
        return $request->validate([
            'calendar_id' => ['nullable', 'string'],
            'query' => ['required', 'string', 'min:1'],
            'time_min' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'time_max' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:time_min'],
            'timezone' => ['required', 'timezone'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:2500'],
            'page_token' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'calendar_id' => $schema->string()->nullable(),
            'query' => $schema->string()->required(),
            'time_min' => $schema->string()->required()->description('RFC3339 datetime with explicit offset. Use end-user local timezone intent.'),
            'time_max' => $schema->string()->required()->description('RFC3339 datetime with explicit offset. Use end-user local timezone intent.'),
            'timezone' => $schema->string()->required()->description('IANA timezone for end-user local intent (example: Europe/Kyiv). Do not assume UTC unless user explicitly requested UTC.'),
            'max_results' => $schema->integer()->min(1)->max(2500)->nullable(),
            'page_token' => $schema->string()->nullable(),
        ];
    }
}
