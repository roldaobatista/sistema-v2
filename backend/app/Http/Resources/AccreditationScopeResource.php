<?php

namespace App\Http\Resources;

use App\Models\AccreditationScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccreditationScope
 */
class AccreditationScopeResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accreditation_number' => $this->accreditation_number,
            'accrediting_body' => $this->accrediting_body,
            'scope_description' => $this->scope_description,
            'equipment_categories' => $this->equipment_categories,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'certificate_file' => $this->certificate_file,
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
