<?php

namespace App\Http\Resources;

use App\Models\AnalyticsDataset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnalyticsDataset
 */
class AnalyticsDatasetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'source_modules' => $this->source_modules,
            'query_definition' => $this->query_definition,
            'refresh_strategy' => $this->refresh_strategy,
            'cache_ttl_minutes' => $this->cache_ttl_minutes,
            'last_refreshed_at' => $this->last_refreshed_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
