<?php

namespace App\Http\Resources;

use App\Models\EmbeddedDashboard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmbeddedDashboard
 */
class EmbeddedDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'provider' => $this->provider,
            'embed_url' => $this->embed_url,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
