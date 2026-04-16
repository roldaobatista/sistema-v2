<?php

namespace App\Http\Resources;

use App\Models\ClientPortalUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientPortalUser
 */
class ClientPortalUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'last_login_at' => $this->when(
                array_key_exists('last_login_at', $this->resource->getAttributes()),
                fn () => $this->last_login_at?->toIso8601String(),
            ),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'document' => $this->customer->document,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
        ];
    }
}
