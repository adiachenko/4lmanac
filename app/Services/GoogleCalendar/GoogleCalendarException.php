<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use RuntimeException;

class GoogleCalendarException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $errorCode,
        public int $statusCode,
        string $message,
        public array $context = [],
    ) {
        parent::__construct($message, $statusCode);
    }
}
