<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Carbon\CarbonImmutable;
use Closure;
use Throwable;

trait InteractsWithTimezoneOffsetValidation
{
    protected function validateDatetimeOffsetMatchesTimezone(
        string $attribute,
        mixed $datetime,
        mixed $timezone,
        Closure $fail,
    ): void {
        if (! is_string($datetime) || ! is_string($timezone) || $datetime === '' || $timezone === '') {
            return;
        }

        if ($this->datetimeOffsetMatchesTimezone($datetime, $timezone)) {
            return;
        }

        $readableAttribute = str_replace('_', ' ', $attribute);

        $fail("The {$readableAttribute} offset must match the provided timezone at that datetime.");
    }

    protected function datetimeOffsetMatchesTimezone(string $datetime, string $timezone): bool
    {
        try {
            $parsedDateTime = CarbonImmutable::parse($datetime);
            $timezoneOffsetAtInstant = $parsedDateTime->setTimezone($timezone)->getOffset();

            return $parsedDateTime->getOffset() === $timezoneOffsetAtInstant;
        } catch (Throwable) {
            return false;
        }
    }
}
