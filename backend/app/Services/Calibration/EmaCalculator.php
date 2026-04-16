<?php

namespace App\Services\Calibration;

/**
 * Calculates Maximum Permissible Errors (EMA) per Portaria INMETRO nº 157/2022
 * and OIML R76-1:2006.
 *
 * EMA table by accuracy class and load range (in multiples of "e").
 * Verification types: initial/subsequent use ×1, in_use (supervision) uses ×2.
 * Uses BCMath for pure precision avoiding float rounding errors.
 */
class EmaCalculator
{
    /**
     * EMA thresholds per class.
     * Each entry: [max_multiples_of_e, ema_multiples_of_e]
     * The last entry uses PHP_FLOAT_MAX as upper bound (unbounded).
     */
    private const EMA_TABLE = [
        'I' => [
            [50000,  '0.5'],
            [200000, '1.0'],
            [PHP_FLOAT_MAX, '1.5'],
        ],
        'II' => [
            [5000,   '0.5'],
            [20000,  '1.0'],
            [100000, '1.5'],
        ],
        'III' => [
            [500,  '0.5'],
            [2000, '1.0'],
            [10000, '1.5'],
        ],
        'IIII' => [
            [50,  '0.5'],
            [200, '1.0'],
            [1000, '1.5'],
        ],
    ];

    /**
     * Calculate the Maximum Permissible Error for a given measurement point.
     *
     * @param  string  $class  Accuracy class: I, II, III, or IIII
     * @param  float  $eValue  Verification division value "e" (same unit as loadValue)
     * @param  float  $loadValue  The load/mass being measured (same unit as eValue)
     * @param  string  $verificationType  initial|subsequent|in_use
     * @return float EMA in the same unit as eValue/loadValue (absolute value, ±)
     */
    public static function calculate(
        string $class,
        float $eValue,
        float $loadValue,
        string $verificationType = 'initial'
    ): float {
        $class = strtoupper(trim($class));
        if (! isset(self::EMA_TABLE[$class])) {
            throw new \InvalidArgumentException("Unknown accuracy class: {$class}. Valid: I, II, III, IIII");
        }

        if ($eValue <= 0) {
            throw new \InvalidArgumentException("Verification division 'e' must be > 0, got: {$eValue}");
        }

        $eValueStr = self::fmt($eValue);
        $loadValueStr = self::fmt($loadValue);

        $multiplesOfE = bcdiv($loadValueStr, $eValueStr, 6);
        $emaMultipleStr = self::findEmaMultiple($class, (float) $multiplesOfE);

        $ema = bcmul($emaMultipleStr, $eValueStr, 6);

        // Portaria 157/2022: in-use (supervision) EMAs are 2× the initial/subsequent
        if ($verificationType === 'in_use') {
            $ema = bcmul($ema, '2', 6);
        }

        return (float) round((float) $ema, 6);
    }

    /**
     * Calculate EMAs for multiple measurement points at once.
     *
     * @return array<int, array{load: float, ema: float, multiples_of_e: float}>
     */
    public static function calculateForPoints(
        string $class,
        float $eValue,
        array $loadValues,
        string $verificationType = 'initial'
    ): array {
        return array_map(fn (float $load) => [
            'load' => $load,
            'ema' => self::calculate($class, $eValue, $load, $verificationType),
            'multiples_of_e' => $eValue > 0 ? (float) round((float) bcdiv(self::fmt($load), self::fmt($eValue), 6), 2) : 0,
        ], $loadValues);
    }

    /**
     * Suggest measurement points based on equipment capacity.
     * Returns 5 points at 0%, 25%, 50%, 75%, 100% of capacity.
     *
     * @return array<int, array{load: float, percentage: int, ema: float}>
     */
    public static function suggestPoints(
        string $class,
        float $eValue,
        float $maxCapacity,
        string $verificationType = 'initial'
    ): array {
        $percentages = [0, 25, 50, 75, 100];
        $points = [];

        foreach ($percentages as $pct) {
            $capacityStr = self::fmt($maxCapacity);
            $pctStr = self::fmt($pct);

            $mul = bcmul($capacityStr, $pctStr, 6);
            $loadStr = bcdiv($mul, '100', 4);
            $load = (float) $loadStr;

            $points[] = [
                'load' => $load,
                'percentage' => $pct,
                'ema' => $pct === 0 ? 0.0 : self::calculate($class, $eValue, $load, $verificationType),
                'multiples_of_e' => $eValue > 0 ? (float) round((float) bcdiv(self::fmt($load), self::fmt($eValue), 6), 2) : 0,
            ];
        }

        return $points;
    }

    /**
     * Suggest eccentricity test load (≈ 1/3 of max capacity).
     */
    public static function suggestEccentricityLoad(float $maxCapacity): float
    {
        return (float) round((float) bcdiv(self::fmt($maxCapacity), '3', 6), 4);
    }

    /**
     * Suggest repeatability test load (≈ 50-66% of max capacity).
     */
    public static function suggestRepeatabilityLoad(float $maxCapacity): float
    {
        return (float) round((float) bcmul(self::fmt($maxCapacity), '0.5', 6), 4);
    }

    /**
     * Check if a given error is within the EMA (conforming).
     *
     * @deprecated Use ConformityAssessmentService for full ISO 17025 §7.8.6 evaluation.
     *             This method ignores measurement uncertainty and only matches
     *             pure JCGM 106 simple acceptance (w=0). Kept for legacy callers.
     */
    public static function isConforming(float $error, float $ema): bool
    {
        $errorStr = self::fmt(abs($error));
        $emaStr = self::fmt(abs($ema));

        // bccomp returns 1 if left > right. So if errorStr > emaStr, it's false.
        return bccomp($errorStr, $emaStr, 6) <= 0;
    }

    /**
     * Check if a given error is conforming considering expanded uncertainty U.
     *
     * Conservative Brazilian RBC convention (aligned with INMETRO Portaria 157/2022):
     *   conforming iff |err| + U <= |EMA|
     *
     * Use this for binary "simple" decision rule when uncertainty must be
     * accounted for. For guard_band or shared_risk, use ConformityAssessmentService.
     */
    public static function isConformingWithUncertainty(float $error, float $ema, float $u): bool
    {
        $sumStr = self::fmt(abs($error) + abs($u));
        $emaStr = self::fmt(abs($ema));

        return bccomp($sumStr, $emaStr, 6) <= 0;
    }

    /**
     * Get available accuracy classes.
     *
     * @return string[]
     */
    public static function availableClasses(): array
    {
        return array_keys(self::EMA_TABLE);
    }

    private static function findEmaMultiple(string $class, float $multiplesOfE): string
    {
        foreach (self::EMA_TABLE[$class] as [$threshold, $emaMultipleStr]) {
            if ($multiplesOfE <= $threshold) {
                return $emaMultipleStr;
            }
        }

        return '1.5';
    }

    /**
     * Formats float securely to string to avoid typical float string casting issues in bcmath.
     *
     * @return numeric-string
     */
    private static function fmt(float $val): string
    {
        $formatted = sprintf('%.6F', $val);
        if (strpos($formatted, '.') !== false) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        if ($formatted === '' || ! is_numeric($formatted)) {
            return '0';
        }

        return $formatted;
    }
}
