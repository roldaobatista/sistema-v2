<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'address_zip' => $this->address_zip,
            'address_street' => $this->address_street,
            'address_number' => $this->address_number,
            'address_complement' => $this->address_complement,
            'address_neighborhood' => $this->address_neighborhood,
            'address_city' => $this->address_city,
            'address_state' => $this->address_state,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
