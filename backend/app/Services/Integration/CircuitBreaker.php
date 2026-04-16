<?php

namespace App\Services\Integration;

use App\Exceptions\CircuitBreakerException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Generic Circuit Breaker implementation backed by Laravel Cache.
 *
 * States:
 *  - CLOSED   → requests pass through; failures are counted.
 *  - OPEN     → requests are immediately rejected for `$timeoutSeconds`.
 *  - HALF_OPEN → one request is allowed through to test recovery.
 *
 * Usage:
 *   $result = CircuitBreaker::for('auvo_api')
 *       ->withThreshold(5)
 *       ->withTimeout(120)
 *       ->execute(fn () => $client->get('/endpoint'));
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    private const DEFAULT_THRESHOLD = 5;

    private const DEFAULT_TIMEOUT = 120;

    /** @var array<string, self> Registry of all created circuit breakers */
    private static array $registry = [];

    private string $service;

    private int $threshold;

    private int $timeoutSeconds;

    private function __construct(string $service)
    {
        $this->service = $service;
        $this->threshold = self::DEFAULT_THRESHOLD;
        $this->timeoutSeconds = self::DEFAULT_TIMEOUT;

        self::$registry[$service] = $this;
    }

    /**
     * Create a circuit breaker for the given service identifier.
     */
    public static function for(string $service): self
    {
        return new self($service);
    }

    public function withThreshold(int $threshold): self
    {
        $this->threshold = max(1, $threshold);

        return $this;
    }

    public function withTimeout(int $seconds): self
    {
        $this->timeoutSeconds = max(1, $seconds);

        return $this;
    }

    /**
     * Execute the callable if the circuit allows it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws CircuitBreakerException when the circuit is OPEN
     * @throws \Throwable re-throws any exception from the callback after recording the failure
     */
    public function execute(callable $callback): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            $remaining = $this->getSecondsUntilRetry();

            throw new CircuitBreakerException($this->service, $remaining);
        }

        try {
            $result = $callback();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            throw $e;
        }
    }

    /**
     * Try to execute the callable, returning a fallback value when the circuit is open
     * or the callable throws.  Never throws.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    public function executeOrFallback(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $this->execute($callback);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    // ─── State inspection (useful for monitoring / admin panels) ────

    public function getState(): string
    {
        $openUntil = Cache::get($this->cacheKey('open_until'));

        if ($openUntil !== null) {
            if (now()->timestamp < (int) $openUntil) {
                return self::STATE_OPEN;
            }

            // Timeout elapsed → transition to half-open
            return self::STATE_HALF_OPEN;
        }

        return self::STATE_CLOSED;
    }

    public function isOpen(): bool
    {
        return $this->getState() === self::STATE_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->getState() === self::STATE_CLOSED;
    }

    /**
     * Manually reset the circuit to CLOSED.
     */
    public function reset(): void
    {
        Cache::forget($this->cacheKey('failures'));
        Cache::forget($this->cacheKey('open_until'));

        Log::info('CircuitBreaker: manually reset', ['service' => $this->service]);
    }

    /**
     * Get the number of consecutive failures.
     */
    public function getFailureCount(): int
    {
        return (int) Cache::get($this->cacheKey('failures'), 0);
    }

    /**
     * Get the service identifier.
     */
    public function getServiceName(): string
    {
        return $this->service;
    }

    /**
     * List all registered service names.
     *
     * @return array<string>
     */
    public static function registeredServices(): array
    {
        return array_keys(self::$registry);
    }

    /**
     * Get health status of all registered circuit breakers.
     *
     * @return array<string, array{state: string, failure_count: int, service: string}>
     */
    public static function statusAll(): array
    {
        $statuses = [];

        foreach (self::$registry as $service => $cb) {
            $statuses[$service] = [
                'service' => $service,
                'state' => $cb->getState(),
                'failure_count' => $cb->getFailureCount(),
            ];
        }

        return $statuses;
    }

    /**
     * Clear the registry (for testing).
     */
    public static function clearRegistry(): void
    {
        self::$registry = [];
    }

    // ─── Internal ──────────────────────────────────────────────

    private function recordSuccess(): void
    {
        $previousState = $this->getState();

        // Reset failures on success
        Cache::forget($this->cacheKey('failures'));
        Cache::forget($this->cacheKey('open_until'));

        if ($previousState !== self::STATE_CLOSED) {
            Log::info('CircuitBreaker: closed after recovery', [
                'service' => $this->service,
                'previous_state' => $previousState,
            ]);
        }
    }

    private function recordFailure(): void
    {
        $failures = $this->incrementFailures();

        if ($failures >= $this->threshold) {
            $this->trip();
        }
    }

    private function incrementFailures(): int
    {
        $key = $this->cacheKey('failures');
        $failures = (int) Cache::get($key, 0) + 1;

        // Store with a generous TTL so stale keys don't linger forever
        Cache::put($key, $failures, $this->timeoutSeconds * 10);

        return $failures;
    }

    private function trip(): void
    {
        $openUntil = now()->addSeconds($this->timeoutSeconds)->timestamp;
        Cache::put($this->cacheKey('open_until'), $openUntil, $this->timeoutSeconds + 60);

        // Reset failure count so the next half-open probe starts clean
        Cache::forget($this->cacheKey('failures'));

        Log::warning('CircuitBreaker: tripped OPEN', [
            'service' => $this->service,
            'timeout_seconds' => $this->timeoutSeconds,
            'threshold' => $this->threshold,
        ]);
    }

    private function getSecondsUntilRetry(): int
    {
        $openUntil = (int) Cache::get($this->cacheKey('open_until'), 0);
        $remaining = $openUntil - now()->timestamp;

        return max(0, $remaining);
    }

    private function cacheKey(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }
}
