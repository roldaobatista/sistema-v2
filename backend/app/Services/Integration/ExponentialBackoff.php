<?php

namespace App\Services\Integration;

/**
 * Reusable exponential backoff calculator with jitter.
 *
 * Formula: min(maxDelay, baseDelay * 2^attempt) + random(0, jitterRange)
 */
class ExponentialBackoff
{
    /**
     * Calculate the delay in seconds for the given attempt.
     *
     * @param  int  $attempt  Zero-based attempt number (0 = first retry)
     * @param  int  $baseDelay  Base delay in seconds (default: 5)
     * @param  int  $maxDelay  Maximum delay cap in seconds (default: 300)
     * @param  int  $jitterRange  Maximum jitter in seconds (default: equals baseDelay)
     */
    public static function calculate(
        int $attempt,
        int $baseDelay = 5,
        int $maxDelay = 300,
        ?int $jitterRange = null,
    ): int {
        $jitterRange ??= $baseDelay;

        $exponential = $baseDelay * (2 ** max(0, $attempt));
        $capped = min($maxDelay, $exponential);
        $jitter = $jitterRange > 0 ? random_int(0, $jitterRange) : 0;

        return $capped + $jitter;
    }

    /**
     * Return an array of backoff delays suitable for Laravel's $backoff property.
     *
     * @param  int  $maxRetries  Number of retry delays to generate
     */
    public static function sequence(int $maxRetries = 5, int $baseDelay = 5, int $maxDelay = 300): array
    {
        return array_map(
            fn (int $attempt) => self::calculate($attempt, $baseDelay, $maxDelay, 0),
            range(0, $maxRetries - 1)
        );
    }
}
