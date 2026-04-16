<?php

namespace App\Http\Resources;

use App\Models\LgpdDpoConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LgpdDpoConfig
 */
class LgpdDpoConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'dpo_name' => $this->dpo_name,
            'dpo_email' => $this->dpo_email,
            'dpo_phone' => $this->dpo_phone,
            'is_public' => $this->is_public,
            'updated_by' => $this->updated_by,
            'updater' => $this->whenLoaded('updater'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
