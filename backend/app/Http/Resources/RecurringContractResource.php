<?php

namespace App\Http\Resources;

use App\Models\RecurringContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurringContract
 */
class RecurringContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'equipment_id' => $this->equipment_id,
            'assigned_to' => $this->assigned_to,
            'name' => $this->name,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'next_run_date' => $this->next_run_date?->format('Y-m-d'),
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'generated_count' => $this->generated_count ?? 0,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('equipment')) {
            $arr['equipment'] = $this->equipment;
        }
        if ($this->relationLoaded('assignee')) {
            $arr['assignee'] = $this->assignee;
        }
        if ($this->relationLoaded('items')) {
            $arr['items'] = $this->items;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }

        return $arr;
    }
}
