<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Batch
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'batch_number' => $this->code,
            'code' => $this->code,
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'cost_price' => $this->cost_price,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('product')) {
            $arr['product'] = $this->product;
        }
        if ($this->relationLoaded('stocks')) {
            $arr['stocks'] = $this->stocks;
        }

        return $arr;
    }
}
