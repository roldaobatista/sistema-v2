<?php

namespace App\Actions\Calibration;

use App\Models\CalibrationDecisionLog;
use App\Models\EquipmentCalibration;
use App\Services\Calibration\ConformityAssessmentService;
use App\Services\Calibration\Decisions\DecisionInput;
use App\Services\Calibration\Decisions\DecisionOutput;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Avalia a regra de decisão de uma calibração e persiste o resultado.
 *
 * Fluxo:
 *  1. Resolve a regra efetiva (sobrescrita do técnico > acordo da OS > 'simple').
 *  2. Extrai inputs (max_error_found, EMA, U).
 *  3. Chama ConformityAssessmentService.
 *  4. Persiste resultado em equipment_calibrations + cria log de auditoria.
 */
class EvaluateCalibrationDecisionAction
{
    public function __construct(
        private readonly ConformityAssessmentService $service,
    ) {}

    /**
     * @param  array<string,mixed>  $payload  validated EvaluateDecisionRequest data
     */
    public function execute(EquipmentCalibration $calibration, int $userId, array $payload): EquipmentCalibration
    {
        $calibration->loadMissing(['workOrder', 'readings']);

        $rule = $payload['rule'];
        $k = (float) $payload['coverage_factor_k'];
        $confidence = (float) ($payload['confidence_level'] ?? 95.45);
        $notes = $payload['notes'] ?? null;

        $absError = abs((float) ($calibration->max_error_found ?? 0));
        $ema = abs((float) ($calibration->max_permissible_error ?? 0));
        $u = $this->resolveExpandedUncertainty($calibration);

        if ($ema <= 0.0) {
            throw new RuntimeException('Calibration has no max_permissible_error (EMA) set.');
        }

        $input = new DecisionInput(
            rule: $rule,
            measuredError: $absError,
            limit: $ema,
            expandedUncertainty: $u,
            coverageFactor: $k,
            guardBandMode: $payload['guard_band_mode'] ?? null,
            guardBandValue: isset($payload['guard_band_value']) ? (float) $payload['guard_band_value'] : null,
            producerRiskAlpha: isset($payload['producer_risk_alpha']) ? (float) $payload['producer_risk_alpha'] : null,
            consumerRiskBeta: isset($payload['consumer_risk_beta']) ? (float) $payload['consumer_risk_beta'] : null,
        );

        $output = $this->service->evaluate($input);

        return DB::transaction(function () use ($calibration, $userId, $rule, $k, $confidence, $notes, $payload, $input, $output) {
            $calibration->decision_rule = $rule;
            $calibration->coverage_factor_k = $this->decimal($k);
            $calibration->confidence_level = $this->decimal($confidence);
            $calibration->guard_band_mode = $payload['guard_band_mode'] ?? null;
            $calibration->guard_band_value = isset($payload['guard_band_value']) ? $this->decimal((float) $payload['guard_band_value']) : null;
            $calibration->producer_risk_alpha = isset($payload['producer_risk_alpha']) ? $this->decimal((float) $payload['producer_risk_alpha']) : null;
            $calibration->consumer_risk_beta = isset($payload['consumer_risk_beta']) ? $this->decimal((float) $payload['consumer_risk_beta']) : null;

            $calibration->decision_result = $output->result;
            $calibration->decision_z_value = $output->zValue !== null ? $this->decimal($output->zValue) : null;
            $calibration->decision_false_accept_prob = $output->falseAcceptProbability !== null ? $this->decimal($output->falseAcceptProbability) : null;
            $calibration->decision_guard_band_applied = $output->guardBandApplied !== null ? $this->decimal($output->guardBandApplied) : null;
            $calibration->decision_calculated_at = now();
            $calibration->decision_calculated_by = $userId;
            $calibration->decision_notes = $notes;

            $calibration->save();

            CalibrationDecisionLog::create([
                'tenant_id' => $calibration->tenant_id,
                'equipment_calibration_id' => $calibration->id,
                'user_id' => $userId,
                'decision_rule' => $rule,
                'inputs' => $this->serializeInput($input),
                'outputs' => $this->serializeOutput($output),
                'engine_version' => ConformityAssessmentService::ENGINE_VERSION,
            ]);

            return $calibration->fresh(['workOrder', 'readings', 'decisionCalculator']);
        });
    }

    private function resolveExpandedUncertainty(EquipmentCalibration $calibration): float
    {
        // Prefere o maior U expandido entre as readings
        $maxFromReadings = $calibration->readings
            ->map(fn ($r) => (float) ($r->expanded_uncertainty ?? 0))
            ->max();

        if ($maxFromReadings && $maxFromReadings > 0) {
            return (float) $maxFromReadings;
        }

        // Fallback: U direto do registro de calibração
        return (float) ($calibration->uncertainty ?? 0);
    }

    /**
     * @return numeric-string
     */
    private function decimal(float|int|string $value): string
    {
        return Decimal::string($value);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeInput(DecisionInput $in): array
    {
        return [
            'rule' => $in->rule,
            'measured_error' => $in->measuredError,
            'limit' => $in->limit,
            'expanded_uncertainty' => $in->expandedUncertainty,
            'coverage_factor' => $in->coverageFactor,
            'guard_band_mode' => $in->guardBandMode,
            'guard_band_value' => $in->guardBandValue,
            'producer_risk_alpha' => $in->producerRiskAlpha,
            'consumer_risk_beta' => $in->consumerRiskBeta,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeOutput(DecisionOutput $out): array
    {
        return [
            'result' => $out->result,
            'z_value' => $out->zValue,
            'false_accept_probability' => $out->falseAcceptProbability,
            'guard_band_applied' => $out->guardBandApplied,
            'rule_applied' => $out->ruleApplied,
            'trace' => $out->trace,
        ];
    }
}
