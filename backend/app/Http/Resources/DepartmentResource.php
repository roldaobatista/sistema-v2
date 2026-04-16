<?php

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'manager_id' => $this->manager_id,
            'cost_center' => $this->cost_center,
            'is_active' => $this->is_active,
            'parent' => new self($this->whenLoaded('parent')),
            'manager' => $this->whenLoaded('manager'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
