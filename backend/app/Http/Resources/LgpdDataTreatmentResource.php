<?php

namespace App\Http\Resources;

use App\Models\LgpdDataTreatment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LgpdDataTreatment
 */
class LgpdDataTreatmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'data_category' => $this->data_category,
            'purpose' => $this->purpose,
            'legal_basis' => $this->legal_basis,
            'description' => $this->description,
            'data_types' => $this->data_types,
            'retention_period' => $this->retention_period,
            'retention_legal_basis' => $this->retention_legal_basis,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
