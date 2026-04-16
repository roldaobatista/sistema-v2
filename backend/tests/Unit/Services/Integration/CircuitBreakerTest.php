<?php

namespace Tests\Unit\Services\Integration;

use App\Exceptions\CircuitBreakerException;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean out any circuit breaker state before each test
        Cache::flush();
    }

    public function test_circuit_starts_closed(): void
    {
        $cb = CircuitBreaker::for('test_service');

        $this->assertTrue($cb->isClosed());
        $this->assertFalse($cb->isOpen());
        $this->assertEquals(0, $cb->getFailureCount());
    }

    public function test_success_passes_through_and_returns_value(): void
    {
        $result = CircuitBreaker::for('test_service')
            ->execute(fn () => 'hello');

        $this->assertEquals('hello', $result);
    }

    public function test_failure_increments_counter(): void
    {
        $cb = CircuitBreaker::for('test_service')->withThreshold(5);

        try {
            $cb->execute(fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertEquals(1, $cb->getFailureCount());
        $this->assertTrue($cb->isClosed());
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        $threshold = 3;
        $cb = CircuitBreaker::for('test_open')->withThreshold($threshold)->withTimeout(60);

        for ($i = 0; $i < $threshold; $i++) {
            try {
                $cb->execute(fn () => throw new \RuntimeException("fail #{$i}"));
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertTrue($cb->isOpen());
    }

    public function test_open_circuit_rejects_immediately(): void
    {
        $cb = CircuitBreaker::for('test_reject')->withThreshold(1)->withTimeout(60);

        // Trip the circuit
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        // Now it should reject without calling the callback
        $callbackExecuted = false;
        $this->expectException(CircuitBreakerException::class);

        $cb->execute(function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'should not reach here';
        });

        $this->assertFalse($callbackExecuted);
    }

    public function test_circuit_breaker_exception_has_service_info(): void
    {
        $cb = CircuitBreaker::for('my_service')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected — circuit is now open
        }

        try {
            $cb->execute(fn () => 'nope');
            $this->fail('Expected CircuitBreakerException');
        } catch (CircuitBreakerException $e) {
            $this->assertEquals('my_service', $e->getServiceName());
            $this->assertGreaterThan(0, $e->getRetryAfterSeconds());
            $this->assertLessThanOrEqual(60, $e->getRetryAfterSeconds());
        }
    }

    public function test_circuit_transitions_to_half_open_after_timeout(): void
    {
        $cb = CircuitBreaker::for('test_half_open')->withThreshold(1)->withTimeout(1);

        // Trip the circuit
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($cb->isOpen());

        // Simulate timeout by manipulating cache
        Cache::forget('circuit_breaker:test_half_open:open_until');
        Cache::put('circuit_breaker:test_half_open:open_until', now()->subSecond()->timestamp, 60);

        // Should be half-open now (timeout elapsed)
        $this->assertFalse($cb->isOpen());
        $this->assertEquals('half_open', $cb->getState());
    }

    public function test_success_in_half_open_closes_circuit(): void
    {
        $cb = CircuitBreaker::for('test_recovery')->withThreshold(1)->withTimeout(1);

        // Trip the circuit
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        // Simulate timeout
        Cache::put('circuit_breaker:test_recovery:open_until', now()->subSecond()->timestamp, 60);

        // Execute should succeed — this transitions from half_open to closed
        $result = $cb->execute(fn () => 'recovered!');

        $this->assertEquals('recovered!', $result);
        $this->assertTrue($cb->isClosed());
        $this->assertEquals(0, $cb->getFailureCount());
    }

    public function test_failure_in_half_open_reopens_circuit(): void
    {
        $cb = CircuitBreaker::for('test_reopen')->withThreshold(1)->withTimeout(60);

        // Trip the circuit
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        // Simulate timeout (half-open)
        Cache::put('circuit_breaker:test_reopen:open_until', now()->subSecond()->timestamp, 60);

        // Fail again — should re-trip
        try {
            $cb->execute(fn () => throw new \RuntimeException('fail again'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($cb->isOpen());
    }

    public function test_success_resets_failure_counter(): void
    {
        $cb = CircuitBreaker::for('test_reset')->withThreshold(5);

        // Accumulate some failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $cb->execute(fn () => throw new \RuntimeException("fail #{$i}"));
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertEquals(3, $cb->getFailureCount());

        // One success resets
        $cb->execute(fn () => 'success');

        $this->assertEquals(0, $cb->getFailureCount());
        $this->assertTrue($cb->isClosed());
    }

    public function test_each_service_has_independent_circuit(): void
    {
        $cb1 = CircuitBreaker::for('service_a')->withThreshold(1)->withTimeout(60);
        $cb2 = CircuitBreaker::for('service_b')->withThreshold(1)->withTimeout(60);

        // Trip service_a
        try {
            $cb1->execute(fn () => throw new \RuntimeException('trip a'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($cb1->isOpen());
        $this->assertFalse($cb2->isOpen());

        // service_b should still work
        $result = $cb2->execute(fn () => 'b works');
        $this->assertEquals('b works', $result);
    }

    public function test_execute_or_fallback_returns_fallback_on_open_circuit(): void
    {
        $cb = CircuitBreaker::for('test_fallback')->withThreshold(1)->withTimeout(60);

        // Trip
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $result = $cb->executeOrFallback(fn () => 'should not run', 'fallback_value');

        $this->assertEquals('fallback_value', $result);
    }

    public function test_execute_or_fallback_returns_fallback_on_exception(): void
    {
        $cb = CircuitBreaker::for('test_fallback_exc')->withThreshold(5);

        $result = $cb->executeOrFallback(
            fn () => throw new \RuntimeException('oops'),
            'safe_value'
        );

        $this->assertEquals('safe_value', $result);
        // Failure should still be recorded
        $this->assertEquals(1, $cb->getFailureCount());
    }

    public function test_execute_or_fallback_returns_null_by_default(): void
    {
        $cb = CircuitBreaker::for('test_default_null')->withThreshold(1)->withTimeout(60);

        // Trip
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $result = $cb->executeOrFallback(fn () => 'nope');

        $this->assertNull($result);
    }

    public function test_reset_clears_circuit(): void
    {
        $cb = CircuitBreaker::for('test_manual_reset')->withThreshold(1)->withTimeout(60);

        // Trip
        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($cb->isOpen());

        $cb->reset();

        $this->assertTrue($cb->isClosed());
        $this->assertEquals(0, $cb->getFailureCount());
    }

    public function test_custom_threshold_and_timeout(): void
    {
        $cb = CircuitBreaker::for('test_custom')
            ->withThreshold(10)
            ->withTimeout(300);

        // 9 failures should NOT open the circuit
        for ($i = 0; $i < 9; $i++) {
            try {
                $cb->execute(fn () => throw new \RuntimeException("fail #{$i}"));
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertTrue($cb->isClosed());

        // 10th failure should open it
        try {
            $cb->execute(fn () => throw new \RuntimeException('final fail'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($cb->isOpen());
    }
}
