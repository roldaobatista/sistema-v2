<?php

namespace App\Http\Resources;

use App\Models\CltViolation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CltViolation
 */
class CltViolationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'violation_type' => $this->violation_type,
            'severity' => $this->severity,
            'description' => $this->description,
            'resolved' => $this->resolved,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolved_by,
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user'),
            'resolver' => $this->whenLoaded('resolver'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
