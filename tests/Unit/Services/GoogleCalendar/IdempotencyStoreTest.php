<?php

declare(strict_types=1);

use App\Services\GoogleCalendar\IdempotencyStore;
use Illuminate\Support\Facades\File;

test('stores idempotency payload under project root when configured path is relative', function (): void {
    $relativePath = 'storage/framework/testing/google-mcp/relative-idempotency-store.json';
    $expectedPath = base_path($relativePath);
    $unexpectedPath = public_path($relativePath);

    config()->set('services.google_mcp.idempotency_file', $relativePath);

    File::delete([$expectedPath, $unexpectedPath]);
    File::ensureDirectoryExists(dirname($expectedPath));
    File::ensureDirectoryExists(dirname($unexpectedPath));

    $originalWorkingDirectory = getcwd();

    expect($originalWorkingDirectory)->toBeString();

    try {
        chdir(public_path());

        $store = new IdempotencyStore;
        $result = $store->run('create_event', 'idem-1', ['summary' => 'test'], static fn (): array => ['id' => 'evt-1']);

        expect($result['replayed'])->toBeFalse();
    } finally {
        chdir((string) $originalWorkingDirectory);
    }

    expect(File::exists($expectedPath))->toBeTrue();
    expect(File::exists($unexpectedPath))->toBeFalse();

    File::delete($expectedPath);
});
