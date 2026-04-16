<?php

namespace App\Http\Resources;

use App\Models\ReconciliationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationRule
 */
class ReconciliationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'match_field' => $this->match_field,
            'match_operator' => $this->match_operator,
            'match_value' => $this->match_value,
            'match_amount_min' => $this->match_amount_min,
            'match_amount_max' => $this->match_amount_max,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'category' => $this->category,
            'customer_id' => $this->customer_id,
            'supplier_id' => $this->supplier_id,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'times_applied' => $this->times_applied,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer ? $this->customer->only(['id', 'name']) : null;
        }
        if ($this->relationLoaded('supplier')) {
            $arr['supplier'] = $this->supplier ? $this->supplier->only(['id', 'name']) : null;
        }

        return $arr;
    }
}
