<?php

declare(strict_types=1);

test('has welcome page', function (): void {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('Yep, it’s alive. 👀');
});
