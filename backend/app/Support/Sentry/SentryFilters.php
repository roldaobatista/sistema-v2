<?php

namespace App\Support\Sentry;

use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SentryFilters
{
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        $exception = $hint?->exception;

        if ($exception instanceof ValidationException) {
            return null;
        }

        if (
            $exception instanceof HttpExceptionInterface
            && in_array($exception->getStatusCode(), [401, 403, 404, 422], true)
        ) {
            return null;
        }

        return $event;
    }

    public static function beforeSendTransaction(Event $event): ?Event
    {
        $transaction = (string) $event->getTransaction();

        if ($transaction === '') {
            return $event;
        }

        foreach (['/up', '/api/health', '/telescope', '/_boost'] as $ignoredFragment) {
            if (str_contains($transaction, $ignoredFragment)) {
                return null;
            }
        }

        return $event;
    }
}
