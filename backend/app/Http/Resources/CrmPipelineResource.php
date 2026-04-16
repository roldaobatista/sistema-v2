<?php

namespace App\Http\Resources;

use App\Models\CrmPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmPipeline
 */
class CrmPipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('stages')) {
            $arr['stages'] = $this->stages;
        }

        return $arr;
    }
}
