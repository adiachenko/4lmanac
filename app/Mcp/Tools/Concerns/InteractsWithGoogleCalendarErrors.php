<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Services\GoogleCalendar\GoogleCalendarException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

trait InteractsWithGoogleCalendarErrors
{
    protected function errorResponse(GoogleCalendarException $exception): ResponseFactory
    {
        return Response::make(
            Response::error($exception->getMessage())
        )->withStructuredContent([
            'error' => [
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
                'http_status' => $exception->statusCode,
                'context' => $exception->context,
            ],
        ]);
    }
}
