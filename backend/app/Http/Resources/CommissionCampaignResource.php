<?php

namespace App\Http\Resources;

use App\Models\CommissionCampaign;
use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommissionCampaign
 */
class CommissionCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'multiplier' => $this->multiplier,
            'applies_to_role' => CommissionRule::normalizeRole($this->applies_to_role),
            'applies_to_calculation_type' => $this->applies_to_calculation_type,
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'ends_at' => $this->ends_at?->format('Y-m-d'),
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
