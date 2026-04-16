<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a circuit breaker is open and the protected operation cannot proceed.
 */
class CircuitBreakerException extends RuntimeException
{
    public function __construct(
        private readonly string $serviceName,
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct(
            "Circuit breaker open for '{$serviceName}'. Retry after {$retryAfterSeconds}s."
        );
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
