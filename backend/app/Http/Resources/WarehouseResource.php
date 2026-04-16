<?php

namespace App\Http\Resources;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'user_id' => $this->user_id,
            'vehicle_id' => $this->vehicle_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('user')) {
            $arr['user'] = $this->user;
        }
        if ($this->relationLoaded('vehicle')) {
            $arr['vehicle'] = $this->vehicle;
        }

        return $arr;
    }
}
