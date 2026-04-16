<?php

namespace App\Http\Resources;

use App\Models\StandardWeight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StandardWeight
 */
class StandardWeightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'code' => $this->code,
            'nominal_value' => $this->nominal_value,
            'unit' => $this->unit,
            'serial_number' => $this->serial_number,
            'manufacturer' => $this->manufacturer,
            'precision_class' => $this->precision_class,
            'material' => $this->material,
            'shape' => $this->shape,
            'certificate_number' => $this->certificate_number,
            'certificate_date' => $this->certificate_date?->format('Y-m-d'),
            'certificate_expiry' => $this->certificate_expiry?->format('Y-m-d'),
            'certificate_file' => $this->certificate_file,
            'laboratory' => $this->laboratory,
            'status' => $this->status,
            'status_label' => StandardWeight::STATUSES[$this->status]['label'] ?? $this->status,
            'certificate_status' => $this->certificate_status,
            'display_name' => $this->display_name,
            'wear_rate_percentage' => $this->wear_rate_percentage,
            'expected_failure_date' => $this->expected_failure_date?->format('Y-m-d'),
            'laboratory_accreditation' => $this->laboratory_accreditation,
            'traceability_chain' => $this->traceability_chain,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('calibrations')) {
            $arr['calibrations'] = $this->calibrations;
        }

        return $arr;
    }
}
