<?php

namespace App\Http\Resources;

use App\Models\ServiceCallTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCallTemplate
 */
class ServiceCallTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'priority' => $this->priority,
            'observations' => $this->observations,
            'equipment_ids' => $this->equipment_ids,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
