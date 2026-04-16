<?php

namespace App\Http\Resources;

use App\Models\EquipmentCalibration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentCalibration
 */
class EquipmentCalibrationResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'work_order_id' => $this->work_order_id,
            'calibration_date' => $this->calibration_date,
            'next_due_date' => $this->next_due_date,
            'calibration_type' => $this->calibration_type,
            'result' => $this->result,
            'certificate_number' => $this->certificate_number,
            'max_permissible_error' => $this->max_permissible_error,
            'max_error_found' => $this->max_error_found,
            'mass_unit' => $this->mass_unit,
            'conformity_declaration' => $this->conformity_declaration,

            'decision' => [
                'rule' => $this->decision_rule,
                'result' => $this->decision_result,
                'coverage_factor_k' => $this->coverage_factor_k,
                'confidence_level' => $this->confidence_level,
                'guard_band_mode' => $this->guard_band_mode,
                'guard_band_value' => $this->guard_band_value,
                'guard_band_applied' => $this->decision_guard_band_applied,
                'producer_risk_alpha' => $this->producer_risk_alpha,
                'consumer_risk_beta' => $this->consumer_risk_beta,
                'z_value' => $this->decision_z_value,
                'false_accept_probability' => $this->decision_false_accept_prob,
                'calculated_at' => $this->decision_calculated_at,
                'calculated_by' => $this->whenLoaded('decisionCalculator', fn () => $this->decisionCalculator ? [
                    'id' => $this->decisionCalculator->id,
                    'name' => $this->decisionCalculator->name,
                ] : null),
                'notes' => $this->decision_notes,
            ],
        ];
    }
}
