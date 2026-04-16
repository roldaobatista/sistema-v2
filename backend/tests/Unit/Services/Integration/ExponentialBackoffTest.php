<?php

namespace Tests\Unit\Services\Integration;

use App\Services\Integration\ExponentialBackoff;
use Tests\TestCase;

class ExponentialBackoffTest extends TestCase
{
    public function test_first_attempt_returns_base_delay_plus_jitter(): void
    {
        $delay = ExponentialBackoff::calculate(0, 5, 300, 0);

        $this->assertEquals(5, $delay);
    }

    public function test_delay_doubles_each_attempt(): void
    {
        $delay0 = ExponentialBackoff::calculate(0, 5, 300, 0);
        $delay1 = ExponentialBackoff::calculate(1, 5, 300, 0);
        $delay2 = ExponentialBackoff::calculate(2, 5, 300, 0);
        $delay3 = ExponentialBackoff::calculate(3, 5, 300, 0);

        $this->assertEquals(5, $delay0);
        $this->assertEquals(10, $delay1);
        $this->assertEquals(20, $delay2);
        $this->assertEquals(40, $delay3);
    }

    public function test_delay_is_capped_at_max(): void
    {
        $delay = ExponentialBackoff::calculate(20, 5, 300, 0);

        $this->assertEquals(300, $delay);
    }

    public function test_jitter_adds_randomness(): void
    {
        // With jitter range of 10, delay should be between base and base + 10
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = ExponentialBackoff::calculate(0, 5, 300, 10);
        }

        $this->assertGreaterThanOrEqual(5, min($results));
        $this->assertLessThanOrEqual(15, max($results));
    }

    public function test_sequence_returns_correct_number_of_delays(): void
    {
        $sequence = ExponentialBackoff::sequence(5, 5, 300);

        $this->assertCount(5, $sequence);
        $this->assertEquals(5, $sequence[0]);
        $this->assertEquals(10, $sequence[1]);
        $this->assertEquals(20, $sequence[2]);
        $this->assertEquals(40, $sequence[3]);
        $this->assertEquals(80, $sequence[4]);
    }

    public function test_negative_attempt_is_treated_as_zero(): void
    {
        $delay = ExponentialBackoff::calculate(-1, 5, 300, 0);

        $this->assertEquals(5, $delay);
    }

    public function test_default_jitter_range_equals_base_delay(): void
    {
        // With default jitter (null → equals baseDelay of 5), delay ranges 5..10
        $results = [];
        for ($i = 0; $i < 30; $i++) {
            $results[] = ExponentialBackoff::calculate(0, 5, 300);
        }

        $this->assertGreaterThanOrEqual(5, min($results));
        $this->assertLessThanOrEqual(10, max($results));
    }

    public function test_zero_jitter_range_produces_deterministic_output(): void
    {
        $delay1 = ExponentialBackoff::calculate(2, 10, 1000, 0);
        $delay2 = ExponentialBackoff::calculate(2, 10, 1000, 0);

        $this->assertEquals($delay1, $delay2);
        $this->assertEquals(40, $delay1);
    }
}
