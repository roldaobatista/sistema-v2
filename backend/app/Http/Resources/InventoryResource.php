<?php

namespace App\Http\Resources;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'warehouse_id' => $this->warehouse_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('warehouse')) {
            $arr['warehouse'] = $this->warehouse;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('items')) {
            $arr['items'] = $this->items;
        }

        return $arr;
    }
}
