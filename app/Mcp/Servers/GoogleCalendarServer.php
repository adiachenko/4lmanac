<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateEventTool;
use App\Mcp\Tools\DeleteEventTool;
use App\Mcp\Tools\FindAvailabilityTool;
use App\Mcp\Tools\ListEventsTool;
use App\Mcp\Tools\SearchEventsTool;
use App\Mcp\Tools\UpdateEventTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

class GoogleCalendarServer extends Server
{
    protected string $name = 'Google Calendar MCP Server';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Use these tools to list, search, create, update, delete, and schedule events in Google Calendar.
        Create and update tools support both timed events and all-day events.
        Always pass explicit RFC3339 timestamps with timezone offsets for all datetime inputs.
        Resolve end-user local times in the user's timezone (for example Europe/Kyiv).
        Do not assume UTC unless the user explicitly requested UTC.
    MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListEventsTool::class,
        SearchEventsTool::class,
        CreateEventTool::class,
        UpdateEventTool::class,
        DeleteEventTool::class,
        FindAvailabilityTool::class,
    ];
}
