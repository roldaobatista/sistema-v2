<?php

namespace App\Services\Calibration\Decisions;

/**
 * Entrada para ConformityAssessmentService::evaluate().
 *
 * Referências normativas:
 *  - ISO/IEC 17025:2017 §7.8.6
 *  - ILAC G8:09/2019 §4.2 (regras de decisão)
 *  - ILAC P14:09/2020 (incerteza em calibração)
 *  - JCGM 106:2012 §8 (papel da incerteza na avaliação de conformidade)
 */
final class DecisionInput
{
    public function __construct(
        public readonly string $rule,
        public readonly float $measuredError,
        public readonly float $limit,
        public readonly float $expandedUncertainty,
        public readonly float $coverageFactor = 2.0,
        public readonly ?string $guardBandMode = null,
        public readonly ?float $guardBandValue = null,
        public readonly ?float $producerRiskAlpha = null,
        public readonly ?float $consumerRiskBeta = null,
    ) {}
}
