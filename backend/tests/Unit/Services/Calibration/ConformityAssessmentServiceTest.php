<?php

use App\Services\Calibration\ConformityAssessmentService;
use App\Services\Calibration\Decisions\DecisionInput;

beforeEach(function () {
    $this->service = new ConformityAssessmentService;
});

// ============================================================
// SIMPLE
// ============================================================

it('1. simple: accepts when |err|+U <= EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'simple', measuredError: 0.3, limit: 1.0, expandedUncertainty: 0.5,
    ));

    expect($out->result)->toBe('accept')
        ->and($out->ruleApplied)->toBe('simple');
});

it('2. simple: rejects when |err|+U > EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'simple', measuredError: 0.6, limit: 1.0, expandedUncertainty: 0.5,
    ));

    expect($out->result)->toBe('reject');
});

it('3. simple: accepts at boundary |err|+U = EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'simple', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.5,
    ));

    expect($out->result)->toBe('accept');
});

// ============================================================
// GUARD_BAND
// ============================================================

it('4. guard_band/k_times_u: accepts when |err|+U <= EMA-w', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'guard_band', measuredError: 0.2, limit: 1.0, expandedUncertainty: 0.3,
        guardBandMode: 'k_times_u', guardBandValue: 1.0,
    ));

    expect($out->result)->toBe('accept')
        ->and($out->guardBandApplied)->toEqualWithDelta(0.3, 1e-9);
});

it('5. guard_band/k_times_u: warns in guard zone', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'guard_band', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.3,
        guardBandMode: 'k_times_u', guardBandValue: 1.0,
    ));

    expect($out->result)->toBe('warn');
});

it('6. guard_band/k_times_u: rejects when |err|-U >= EMA+w', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'guard_band', measuredError: 2.0, limit: 1.0, expandedUncertainty: 0.3,
        guardBandMode: 'k_times_u', guardBandValue: 1.0,
    ));

    expect($out->result)->toBe('reject');
});

it('7. guard_band/percent_limit: accepts with w = pct% of EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'guard_band', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.1,
        guardBandMode: 'percent_limit', guardBandValue: 10.0,
    ));

    expect($out->result)->toBe('accept')
        ->and($out->guardBandApplied)->toEqualWithDelta(0.1, 1e-9);
});

it('8. guard_band/fixed_abs: uses absolute w value', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'guard_band', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.1,
        guardBandMode: 'fixed_abs', guardBandValue: 0.2,
    ));

    expect($out->result)->toBe('accept')
        ->and($out->guardBandApplied)->toEqualWithDelta(0.2, 1e-9);
});

// ============================================================
// SHARED_RISK
// ============================================================

it('9. shared_risk: accepts when P_fa <= beta and |err| < EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'shared_risk', measuredError: 0.3, limit: 1.0, expandedUncertainty: 0.5,
        coverageFactor: 2.0, producerRiskAlpha: 0.05, consumerRiskBeta: 0.05,
    ));

    // u_c = 0.25, z = 2.8, P_fa ≈ 0.00256 << 0.05
    expect($out->result)->toBe('accept')
        ->and($out->zValue)->toEqualWithDelta(2.8, 1e-3)
        ->and($out->falseAcceptProbability)->toBeLessThan(0.01);
});

it('10. shared_risk: rejects when P_fr <= alpha and |err| > EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'shared_risk', measuredError: 1.2, limit: 1.0, expandedUncertainty: 0.1,
        coverageFactor: 2.0, producerRiskAlpha: 0.05, consumerRiskBeta: 0.05,
    ));

    // u_c = 0.05, z = 4, P_fr ≈ 3.17e-5 << 0.05
    expect($out->result)->toBe('reject')
        ->and($out->zValue)->toEqualWithDelta(4.0, 1e-3);
});

it('11. shared_risk: warns when P_fa > beta (boundary)', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'shared_risk', measuredError: 0.85, limit: 1.0, expandedUncertainty: 0.2,
        coverageFactor: 2.0, producerRiskAlpha: 0.05, consumerRiskBeta: 0.05,
    ));

    // u_c = 0.1, z = 1.5, P_fa ≈ 0.0668 > 0.05
    expect($out->result)->toBe('warn');
});

// ============================================================
// EDGE CASES & VALIDATION
// ============================================================

it('12. simple with U=0 degenerates to |err| <= EMA', function () {
    $out = $this->service->evaluate(new DecisionInput(
        rule: 'simple', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.0,
    ));

    expect($out->result)->toBe('accept');
});

it('13. throws when guard_band lacks mode/value', function () {
    expect(fn () => $this->service->evaluate(new DecisionInput(
        rule: 'guard_band', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.3,
    )))->toThrow(InvalidArgumentException::class);
});

it('14. throws on unknown rule', function () {
    expect(fn () => $this->service->evaluate(new DecisionInput(
        rule: 'simple_acceptance', measuredError: 0.5, limit: 1.0, expandedUncertainty: 0.3,
    )))->toThrow(InvalidArgumentException::class);
});

it('15. throws when limit (EMA) is 0', function () {
    expect(fn () => $this->service->evaluate(new DecisionInput(
        rule: 'simple', measuredError: 0.5, limit: 0.0, expandedUncertainty: 0.1,
    )))->toThrow(InvalidArgumentException::class);
});
