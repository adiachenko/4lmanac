<?php

declare(strict_types=1);

use App\Mcp\Servers\GoogleCalendarServer;
use App\Mcp\Tools\CreateEventTool;
use App\Mcp\Tools\UpdateEventTool;
use Laravel\Mcp\Server\Transport\FakeTransporter;

test('registers all expected google calendar tools', function (): void {
    $server = app(GoogleCalendarServer::class, ['transport' => new FakeTransporter]);

    $toolNames = $server
        ->createContext()
        ->tools()
        ->map(static fn ($tool): string => $tool->name())
        ->all();

    expect($toolNames)->toBe([
        'list_events',
        'search_events',
        'create_event',
        'update_event',
        'delete_event',
        'find_availability',
    ]);
});

test('publishes timezone instructions that prevent implicit utc assumptions', function (): void {
    $server = app(GoogleCalendarServer::class, ['transport' => new FakeTransporter]);
    $context = $server->createContext();
    $toolsByName = $context->tools()->keyBy(static fn ($tool): string => $tool->name());

    expect($context->instructions)->toContain('Do not assume UTC');
    expect($toolsByName->get('create_event'))->not->toBeNull();
    expect($toolsByName->get('update_event'))->not->toBeNull();

    /** @var CreateEventTool $createTool */
    $createTool = $toolsByName->get('create_event');
    /** @var UpdateEventTool $updateTool */
    $updateTool = $toolsByName->get('update_event');

    expect($createTool->description())->toContain('Do not assume UTC');
    expect($updateTool->description())->toContain('Do not assume UTC');
});
