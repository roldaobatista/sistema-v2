<?php

namespace App\Http\Resources;

use App\Models\EquipmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentModel
 */
class EquipmentModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('products')) {
            $arr['products'] = $this->products;
        }
        if (isset($this->products_count)) {
            $arr['products_count'] = $this->products_count;
        }

        return $arr;
    }
}
