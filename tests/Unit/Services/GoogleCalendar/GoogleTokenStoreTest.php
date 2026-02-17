<?php

declare(strict_types=1);

use App\Services\GoogleCalendar\GoogleTokenStore;
use Illuminate\Support\Facades\File;

test('stores token payload under project root when configured path is relative', function (): void {
    $relativePath = 'storage/framework/testing/google-mcp/relative-token-store.json';
    $expectedPath = base_path($relativePath);
    $unexpectedPath = public_path($relativePath);

    config()->set('services.google_mcp.token_file', $relativePath);

    File::delete([$expectedPath, $unexpectedPath]);
    File::ensureDirectoryExists(dirname($expectedPath));
    File::ensureDirectoryExists(dirname($unexpectedPath));

    $originalWorkingDirectory = getcwd();

    expect($originalWorkingDirectory)->toBeString();

    try {
        chdir(public_path());

        $store = new GoogleTokenStore;
        $store->write([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
        ]);
    } finally {
        chdir((string) $originalWorkingDirectory);
    }

    expect(File::exists($expectedPath))->toBeTrue();
    expect(File::exists($unexpectedPath))->toBeFalse();

    File::delete($expectedPath);
});
