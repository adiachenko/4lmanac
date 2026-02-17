<?php

declare(strict_types=1);

use App\Models\User;

test('hides sensitive attributes when serialized', function (): void {
    $user = new User;
    $user->forceFill([
        'password' => 'plain-password',
        'remember_token' => 'remember-token',
    ]);

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});
