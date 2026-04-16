<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'label' => $this->label ?? $this->display_name ?? $this->name,
            'description' => $this->description,
            'guard_name' => $this->guard_name,
            'tenant_id' => $this->tenant_id,
            'is_protected' => (bool) ($this->is_protected ?? false),
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
            'permissions' => $this->whenLoaded('permissions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
