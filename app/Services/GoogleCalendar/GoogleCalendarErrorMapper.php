<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use Illuminate\Http\Client\Response;

class GoogleCalendarErrorMapper
{
    public function fromResponse(Response $response): GoogleCalendarException
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        $statusCode = $response->status();

        /** @var array<int, array<string, mixed>> $errors */
        $errors = data_get($payload, 'error.errors', []);
        $primaryReasonValue = data_get($errors, '0.reason', '');
        $primaryReason = is_string($primaryReasonValue) ? $primaryReasonValue : '';

        $fallbackMessage = $response->body() === '' ? 'Google Calendar API request failed.' : $response->body();
        $messageValue = data_get($payload, 'error.message', $fallbackMessage);
        $message = is_string($messageValue) ? $messageValue : $fallbackMessage;

        $errorCode = match (true) {
            $statusCode === 400 => 'VALIDATION_ERROR',
            $statusCode === 401 => 'GOOGLE_REAUTH_REQUIRED',
            $statusCode === 403 && in_array($primaryReason, ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'], true) => 'RATE_LIMITED',
            $statusCode === 403 => 'FORBIDDEN',
            $statusCode === 404 => 'NOT_FOUND',
            $statusCode === 409 => 'CONFLICT',
            $statusCode === 429 => 'RATE_LIMITED',
            $statusCode >= 500 => 'UPSTREAM_ERROR',
            default => 'UPSTREAM_ERROR',
        };

        return new GoogleCalendarException(
            errorCode: $errorCode,
            statusCode: $statusCode,
            message: $message,
            context: [
                'google_reason' => $primaryReason,
            ],
        );
    }
}
