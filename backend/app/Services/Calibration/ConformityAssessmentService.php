<?php

namespace App\Services\Calibration;

use App\Services\Calibration\Decisions\DecisionInput;
use App\Services\Calibration\Decisions\DecisionOutput;
use InvalidArgumentException;

/**
 * Motor de avaliação de regras de decisão de conformidade
 * para certificados de calibração.
 *
 * Normas implementadas:
 *  - ISO/IEC 17025:2017 §7.8.6
 *  - ILAC G8:09/2019 §4.2.1 (binary simple), §4.2.2 (guard band), §4.2.3 (shared risk)
 *  - ILAC P14:09/2020 (parâmetros de incerteza, k, U)
 *  - JCGM 106:2012 §8/§9 (papel da incerteza, fórmulas matemáticas)
 *  - Eurachem/CITAC Guide 2nd ed. 2021 (interpretação)
 *
 * Convenção brasileira aplicada (RBC/INMETRO Portaria 157/2022):
 *  - SIMPLE conservador: |err|+U <= EMA  (não puro JCGM, alinhado a ABNT NBR
 *    ISO/IEC 17025 implementada pelo INMETRO para calibração de balanças).
 *  - GUARD_BAND: combina |err|+U com EMA-w (mais conservador que G8 estrito).
 *  - SHARED_RISK: usa u_c = U/k e CDF normal para calcular P_fa / P_fr.
 */
class ConformityAssessmentService
{
    public const ENGINE_VERSION = '1.0';

    public const RULE_SIMPLE = 'simple';

    public const RULE_GUARD_BAND = 'guard_band';

    public const RULE_SHARED_RISK = 'shared_risk';

    public const RESULT_ACCEPT = 'accept';

    public const RESULT_WARN = 'warn';

    public const RESULT_REJECT = 'reject';

    public const GB_K_TIMES_U = 'k_times_u';

    public const GB_PERCENT_LIMIT = 'percent_limit';

    public const GB_FIXED_ABS = 'fixed_abs';

    public function evaluate(DecisionInput $in): DecisionOutput
    {
        $this->validate($in);

        return match ($in->rule) {
            self::RULE_SIMPLE => $this->evaluateSimple($in),
            self::RULE_GUARD_BAND => $this->evaluateGuardBand($in),
            self::RULE_SHARED_RISK => $this->evaluateSharedRisk($in),
            default => throw new InvalidArgumentException("Unknown decision rule: {$in->rule}"),
        };
    }

    /**
     * SIMPLE (convenção brasileira RBC, conservadora):
     *   accept se |err| + U <= EMA
     *   reject caso contrário
     */
    private function evaluateSimple(DecisionInput $in): DecisionOutput
    {
        $absErr = abs($in->measuredError);
        $isAccept = ($absErr + $in->expandedUncertainty) <= $in->limit;

        return new DecisionOutput(
            result: $isAccept ? self::RESULT_ACCEPT : self::RESULT_REJECT,
            zValue: null,
            falseAcceptProbability: null,
            guardBandApplied: null,
            ruleApplied: self::RULE_SIMPLE,
            trace: [
                'formula' => '|err| + U <= EMA',
                'abs_error' => $absErr,
                'U' => $in->expandedUncertainty,
                'EMA' => $in->limit,
                'sum_err_plus_u' => $absErr + $in->expandedUncertainty,
            ],
        );
    }

    /**
     * GUARD_BAND (ILAC G8 §4.2.2 + RBC):
     *   w computado pelo modo (k_times_u | percent_limit | fixed_abs)
     *   accept  se |err| + U <= EMA - w
     *   reject  se |err| - U >= EMA + w
     *   warn    caso contrário (zona de guarda)
     */
    private function evaluateGuardBand(DecisionInput $in): DecisionOutput
    {
        $w = $this->computeGuardBand($in);
        $absErr = abs($in->measuredError);
        $U = $in->expandedUncertainty;
        $EMA = $in->limit;

        if (($absErr + $U) <= ($EMA - $w)) {
            $result = self::RESULT_ACCEPT;
        } elseif (($absErr - $U) >= ($EMA + $w)) {
            $result = self::RESULT_REJECT;
        } else {
            $result = self::RESULT_WARN;
        }

        return new DecisionOutput(
            result: $result,
            zValue: null,
            falseAcceptProbability: null,
            guardBandApplied: $w,
            ruleApplied: self::RULE_GUARD_BAND,
            trace: [
                'formula' => 'accept: |err|+U<=EMA-w ; reject: |err|-U>=EMA+w',
                'mode' => $in->guardBandMode,
                'value' => $in->guardBandValue,
                'w' => $w,
                'abs_error' => $absErr,
                'U' => $U,
                'EMA' => $EMA,
                'lower_bound' => $EMA - $w,
                'upper_bound' => $EMA + $w,
            ],
        );
    }

    /**
     * SHARED_RISK (JCGM 106:2012 §9 + ILAC G8 §4.2.3):
     *   u_c = U / k     (desvio padrão combinado)
     *   se |err| <= EMA:
     *     z = (EMA - |err|) / u_c
     *     P_fa = 1 - Φ(z)              (consumer risk)
     *     accept se P_fa <= β, senão warn
     *   senão:
     *     z = (|err| - EMA) / u_c
     *     P_fr = 1 - Φ(z)              (producer risk)
     *     reject se P_fr <= α, senão warn
     */
    private function evaluateSharedRisk(DecisionInput $in): DecisionOutput
    {
        $absErr = abs($in->measuredError);
        $EMA = $in->limit;
        $u_c = $in->expandedUncertainty / $in->coverageFactor;

        if ($u_c <= 0.0) {
            throw new InvalidArgumentException('shared_risk requires expandedUncertainty > 0');
        }

        $alpha = $in->producerRiskAlpha;
        $beta = $in->consumerRiskBeta;

        if ($absErr <= $EMA) {
            $z = ($EMA - $absErr) / $u_c;
            $pfa = 1.0 - $this->normalCdf($z);
            $result = ($pfa <= $beta) ? self::RESULT_ACCEPT : self::RESULT_WARN;
            $reportedZ = $z;
            $reportedProb = $pfa;
            $traceFormula = 'z=(EMA-|err|)/u_c ; P_fa=1-Φ(z) ; accept se P_fa<=β';
        } else {
            $z = ($absErr - $EMA) / $u_c;
            $pfr = 1.0 - $this->normalCdf($z);
            $result = ($pfr <= $alpha) ? self::RESULT_REJECT : self::RESULT_WARN;
            $reportedZ = $z;
            $reportedProb = $pfr;
            $traceFormula = 'z=(|err|-EMA)/u_c ; P_fr=1-Φ(z) ; reject se P_fr<=α';
        }

        return new DecisionOutput(
            result: $result,
            zValue: $reportedZ,
            falseAcceptProbability: $reportedProb,
            guardBandApplied: null,
            ruleApplied: self::RULE_SHARED_RISK,
            trace: [
                'formula' => $traceFormula,
                'u_c' => $u_c,
                'k' => $in->coverageFactor,
                'abs_error' => $absErr,
                'EMA' => $EMA,
                'z' => $reportedZ,
                'probability' => $reportedProb,
                'alpha' => $alpha,
                'beta' => $beta,
            ],
        );
    }

    private function computeGuardBand(DecisionInput $in): float
    {
        $value = $in->guardBandValue;

        return match ($in->guardBandMode) {
            self::GB_K_TIMES_U => $value * $in->expandedUncertainty,
            self::GB_PERCENT_LIMIT => ($value / 100.0) * $in->limit,
            self::GB_FIXED_ABS => $value,
            default => throw new InvalidArgumentException(
                "Invalid guard_band_mode: {$in->guardBandMode}"
            ),
        };
    }

    private function validate(DecisionInput $in): void
    {
        if (! in_array($in->rule, [self::RULE_SIMPLE, self::RULE_GUARD_BAND, self::RULE_SHARED_RISK], true)) {
            throw new InvalidArgumentException("Unknown decision rule: {$in->rule}");
        }
        if ($in->limit <= 0.0) {
            throw new InvalidArgumentException('limit (EMA) must be > 0');
        }
        if ($in->expandedUncertainty < 0.0) {
            throw new InvalidArgumentException('expandedUncertainty must be >= 0');
        }
        if ($in->coverageFactor <= 0.0) {
            throw new InvalidArgumentException('coverageFactor (k) must be > 0');
        }

        if ($in->rule === self::RULE_GUARD_BAND) {
            if ($in->guardBandMode === null || $in->guardBandValue === null) {
                throw new InvalidArgumentException('guard_band rule requires guardBandMode and guardBandValue');
            }
            if (! in_array($in->guardBandMode, [self::GB_K_TIMES_U, self::GB_PERCENT_LIMIT, self::GB_FIXED_ABS], true)) {
                throw new InvalidArgumentException("Invalid guard_band_mode: {$in->guardBandMode}");
            }
            if ($in->guardBandValue < 0.0) {
                throw new InvalidArgumentException('guardBandValue must be >= 0');
            }
        }

        if ($in->rule === self::RULE_SHARED_RISK) {
            if ($in->producerRiskAlpha === null || $in->consumerRiskBeta === null) {
                throw new InvalidArgumentException('shared_risk requires producerRiskAlpha and consumerRiskBeta');
            }
            if ($in->producerRiskAlpha <= 0.0 || $in->producerRiskAlpha >= 1.0) {
                throw new InvalidArgumentException('producerRiskAlpha must be in (0, 1)');
            }
            if ($in->consumerRiskBeta <= 0.0 || $in->consumerRiskBeta >= 1.0) {
                throw new InvalidArgumentException('consumerRiskBeta must be in (0, 1)');
            }
        }
    }

    /**
     * Aproximação da função distribuição cumulativa normal Φ(x).
     * Algoritmo: Abramowitz & Stegun 26.2.17. Erro absoluto < 7.5e-8.
     * Sem dependência de extensão (ext-stats / ext-bcmath não requeridas).
     */
    private function normalCdf(float $x): float
    {
        $b1 = 0.319381530;
        $b2 = -0.356563782;
        $b3 = 1.781477937;
        $b4 = -1.821255978;
        $b5 = 1.330274429;
        $p = 0.2316419;
        $c = 0.39894228; // 1 / sqrt(2*pi)

        if ($x >= 0.0) {
            $t = 1.0 / (1.0 + $p * $x);

            return 1.0 - $c * exp(-$x * $x / 2.0)
                * ((((($b5 * $t + $b4) * $t + $b3) * $t + $b2) * $t + $b1) * $t);
        }

        $t = 1.0 / (1.0 - $p * $x);

        return $c * exp(-$x * $x / 2.0)
            * ((((($b5 * $t + $b4) * $t + $b3) * $t + $b2) * $t + $b1) * $t);
    }
}
