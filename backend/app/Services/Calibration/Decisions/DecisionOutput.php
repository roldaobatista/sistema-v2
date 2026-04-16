<?php

namespace App\Services\Calibration\Decisions;

/**
 * Saída de ConformityAssessmentService::evaluate().
 *
 *  - $result: 'accept' | 'warn' | 'reject'
 *  - $zValue: distância normalizada (apenas shared_risk)
 *  - $falseAcceptProbability: P_fa ou P_fr (apenas shared_risk)
 *  - $guardBandApplied: w efetivamente usado (apenas guard_band)
 *  - $trace: bag opcional para auditoria/log
 */
final class DecisionOutput
{
    /**
     * @param  array<int|string, mixed>  $trace
     */
    public function __construct(
        public readonly string $result,
        public readonly ?float $zValue,
        public readonly ?float $falseAcceptProbability,
        public readonly ?float $guardBandApplied,
        public readonly string $ruleApplied,
        public readonly array $trace = [],
    ) {}
}
