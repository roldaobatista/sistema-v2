<?php

namespace App\Http\Resources;

use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Position
 */
class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'department_id' => $this->department_id,
            'level' => $this->level,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
