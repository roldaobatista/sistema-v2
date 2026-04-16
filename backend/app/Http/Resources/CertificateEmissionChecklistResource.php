<?php

namespace App\Http\Resources;

use App\Models\CertificateEmissionChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CertificateEmissionChecklist
 */
class CertificateEmissionChecklistResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'equipment_calibration_id' => $this->equipment_calibration_id,
            'verified_by' => $this->verified_by,
            'equipment_identified' => $this->equipment_identified,
            'scope_defined' => $this->scope_defined,
            'critical_analysis_done' => $this->critical_analysis_done,
            'procedure_defined' => $this->procedure_defined,
            'standards_traceable' => $this->standards_traceable,
            'raw_data_recorded' => $this->raw_data_recorded,
            'uncertainty_calculated' => $this->uncertainty_calculated,
            'adjustment_documented' => $this->adjustment_documented,
            'no_undue_interval' => $this->no_undue_interval,
            'conformity_declaration_valid' => $this->conformity_declaration_valid,
            'accreditation_mark_correct' => $this->accreditation_mark_correct,
            'observations' => $this->observations,
            'approved' => $this->approved,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'verifier' => $this->whenLoaded('verifier'),
        ];
    }
}
