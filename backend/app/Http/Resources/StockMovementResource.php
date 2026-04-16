<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'work_order_id' => $this->work_order_id,
            'warehouse_id' => $this->warehouse_id,
            'target_warehouse_id' => $this->target_warehouse_id,
            'batch_id' => $this->batch_id,
            'product_serial_id' => $this->product_serial_id,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('product')) {
            $arr['product'] = $this->product;
        }
        if ($this->relationLoaded('createdByUser')) {
            $arr['created_by_user'] = $this->createdByUser;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder;
        }
        if ($this->relationLoaded('warehouse')) {
            $arr['warehouse'] = $this->warehouse;
        }
        if ($this->relationLoaded('targetWarehouse')) {
            $arr['target_warehouse'] = $this->targetWarehouse;
        }

        return $arr;
    }
}
