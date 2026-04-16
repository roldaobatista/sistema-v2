<?php

namespace App\Http\Resources;

use App\Models\PartsKit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartsKit
 */
class PartsKitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('items')) {
            $arr['items'] = $this->items->map(function ($item) {
                $row = [
                    'id' => $item->id,
                    'type' => $item->type,
                    'reference_id' => $item->reference_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ];
                if ($item->relationLoaded('product') && $item->product) {
                    $row['product'] = $item->product->only(['id', 'name', 'sku']);
                }
                if ($item->relationLoaded('service') && $item->service) {
                    $row['service'] = $item->service->only(['id', 'name']);
                }

                return $row;
            });
        }

        return $arr;
    }
}
