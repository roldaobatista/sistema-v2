<?php

use App\Services\Calibration\CalibrationWizardService;
use App\Services\Calibration\EmaCalculator;

// ── EmaCalculator tests ──

test('calculates EMA for class III initial verification in first range', function () {
    // Class III, first range: up to 500e, EMA = 0.5e
    // e=0.1, load=20 => multiples = 200 (<=500 range) => EMA = 0.5 * 0.1 = 0.05
    $ema = EmaCalculator::calculate('III', 0.1, 20.0, 'initial');

    expect($ema)->toBe(0.05);
});

test('calculates EMA for class III initial verification in second range', function () {
    // Class III, second range: 500 < m <= 2000, EMA = 1.0e
    // e=0.1, load=100 => multiples = 1000 (<=2000 range) => EMA = 1.0 * 0.1 = 0.1
    $ema = EmaCalculator::calculate('III', 0.1, 100.0, 'initial');

    expect($ema)->toBe(0.1);
});

test('calculates EMA for class III initial verification in third range', function () {
    // Class III, third range: > 2000, EMA = 1.5e
    // e=0.1, load=500 => multiples = 5000 (>2000) => EMA = 1.5 * 0.1 = 0.15
    $ema = EmaCalculator::calculate('III', 0.1, 500.0, 'initial');

    expect($ema)->toBe(0.15);
});

test('in_use verification doubles the EMA', function () {
    // Class III, e=0.1, load=20 => initial EMA = 0.05; in_use = 0.10
    $initial = EmaCalculator::calculate('III', 0.1, 20.0, 'initial');
    $inUse = EmaCalculator::calculate('III', 0.1, 20.0, 'in_use');

    expect($inUse)->toBe($initial * 2);
    expect($inUse)->toBe(0.1);
});

test('subsequent verification uses same EMA as initial', function () {
    $initial = EmaCalculator::calculate('III', 0.1, 20.0, 'initial');
    $subsequent = EmaCalculator::calculate('III', 0.1, 20.0, 'subsequent');

    expect($subsequent)->toBe($initial);
});

test('calculates EMA for class I high precision', function () {
    // Class I, e=0.001, load=10 => multiples=10000 (<50000) => EMA = 0.5 * 0.001 = 0.0005
    $ema = EmaCalculator::calculate('I', 0.001, 10.0, 'initial');

    expect($ema)->toBe(0.0005);
});

test('calculates EMA for class II', function () {
    // Class II, e=0.01, load=30 => multiples=3000 (<5000) => EMA = 0.5 * 0.01 = 0.005
    $ema = EmaCalculator::calculate('II', 0.01, 30.0, 'initial');

    expect($ema)->toBe(0.005);
});

test('calculates EMA for class IIII', function () {
    // Class IIII, e=1, load=30 => multiples=30 (<50) => EMA = 0.5 * 1 = 0.5
    $ema = EmaCalculator::calculate('IIII', 1.0, 30.0, 'initial');

    expect($ema)->toBe(0.5);
});

test('throws exception for invalid accuracy class', function () {
    expect(fn () => EmaCalculator::calculate('V', 0.1, 10.0))
        ->toThrow(InvalidArgumentException::class, 'Unknown accuracy class: V');
});

test('throws exception for zero e value', function () {
    expect(fn () => EmaCalculator::calculate('III', 0, 10.0))
        ->toThrow(InvalidArgumentException::class, 'must be > 0');
});

test('throws exception for negative e value', function () {
    expect(fn () => EmaCalculator::calculate('III', -0.1, 10.0))
        ->toThrow(InvalidArgumentException::class);
});

test('calculateForPoints returns EMAs for multiple loads', function () {
    $results = EmaCalculator::calculateForPoints('III', 0.1, [10, 50, 200], 'initial');

    expect($results)->toHaveCount(3);
    expect($results[0]['load'])->toBe(10.0);
    expect($results[0])->toHaveKeys(['load', 'ema', 'multiples_of_e']);
});

test('suggestPoints returns 5 points at standard percentages', function () {
    $points = EmaCalculator::suggestPoints('III', 0.01, 150.0, 'initial');

    expect($points)->toHaveCount(5);
    expect($points[0]['percentage'])->toBe(0);
    expect($points[0]['ema'])->toBe(0.0); // 0% load has 0 EMA
    expect($points[4]['percentage'])->toBe(100);
    expect($points[4]['load'])->toBe(150.0);
});

test('isConforming returns true when error within tolerance', function () {
    expect(EmaCalculator::isConforming(0.04, 0.05))->toBeTrue();
    expect(EmaCalculator::isConforming(-0.04, 0.05))->toBeTrue();
    expect(EmaCalculator::isConforming(0.05, 0.05))->toBeTrue();
});

test('isConforming returns false when error exceeds tolerance', function () {
    expect(EmaCalculator::isConforming(0.06, 0.05))->toBeFalse();
    expect(EmaCalculator::isConforming(-0.06, 0.05))->toBeFalse();
});

test('availableClasses returns I II III IIII', function () {
    $classes = EmaCalculator::availableClasses();

    expect($classes)->toBe(['I', 'II', 'III', 'IIII']);
});

test('suggestEccentricityLoad returns one third of capacity', function () {
    $load = EmaCalculator::suggestEccentricityLoad(150.0);

    expect($load)->toBe(50.0);
});

test('suggestRepeatabilityLoad returns half of capacity', function () {
    $load = EmaCalculator::suggestRepeatabilityLoad(150.0);

    expect($load)->toBe(75.0);
});

// ── CalibrationWizardService tests ──

test('calculateRepeatability computes mean and std deviation', function () {
    $service = new CalibrationWizardService;

    $result = $service->calculateRepeatability([10.0, 10.2, 10.1, 10.15, 10.05]);

    expect($result['n'])->toBe(5);
    expect($result['mean'])->not->toBeNull();
    expect($result['std_deviation'])->not->toBeNull();
    expect($result['uncertainty_type_a'])->not->toBeNull();
    expect($result['mean'])->toBe(10.1);
});

test('calculateRepeatability with single value returns null for std_deviation', function () {
    $service = new CalibrationWizardService;

    $result = $service->calculateRepeatability([42.0]);

    expect($result['mean'])->toBe(42.0);
    expect($result['std_deviation'])->toBeNull();
    expect($result['uncertainty_type_a'])->toBeNull();
    expect($result['n'])->toBe(1);
});

test('calculateRepeatability with empty array returns nulls', function () {
    $service = new CalibrationWizardService;

    $result = $service->calculateRepeatability([]);

    expect($result['mean'])->toBeNull();
    expect($result['n'])->toBe(0);
});

test('calculateExpandedUncertainty computes U correctly', function () {
    $service = new CalibrationWizardService;

    $result = $service->calculateExpandedUncertainty(
        uncertaintyA: 0.01,
        resolution: 0.1,
        weightUncertainty: 0.005,
        k: 2.0
    );

    expect($result)->toHaveKeys([
        'uncertainty_type_a', 'uncertainty_type_b_resolution',
        'uncertainty_weight', 'combined_uncertainty', 'k_factor', 'expanded_uncertainty',
    ]);

    expect($result['k_factor'])->toBe(2.0);

    // u_B_resolution = 0.1 / (2 * sqrt(3)) ~ 0.028868
    // u_combined = sqrt(0.01^2 + 0.028868^2 + 0.005^2) ~ sqrt(0.0001 + 0.000833 + 0.000025) ~ 0.03094
    // expanded = 2 * 0.03094 ~ 0.06189
    expect($result['expanded_uncertainty'])->toBeGreaterThan(0.05);
    expect($result['expanded_uncertainty'])->toBeLessThan(0.08);
});

test('calculateExpandedUncertainty with zero uncertainties returns zero', function () {
    $service = new CalibrationWizardService;

    $result = $service->calculateExpandedUncertainty(
        uncertaintyA: 0.0,
        resolution: 0.0,
        weightUncertainty: 0.0,
        k: 2.0
    );

    expect($result['expanded_uncertainty'])->toBe(0.0);
    expect($result['combined_uncertainty'])->toBe(0.0);
});
