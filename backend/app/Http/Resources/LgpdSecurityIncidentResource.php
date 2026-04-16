<?php

namespace App\Http\Resources;

use App\Models\LgpdSecurityIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LgpdSecurityIncident
 */
class LgpdSecurityIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'protocol' => $this->protocol,
            'severity' => $this->severity,
            'description' => $this->description,
            'affected_data' => $this->affected_data,
            'affected_holders_count' => $this->affected_holders_count,
            'measures_taken' => $this->measures_taken,
            'anpd_notification' => $this->anpd_notification,
            'holders_notified' => $this->holders_notified,
            'holders_notified_at' => $this->holders_notified_at?->toIso8601String(),
            'detected_at' => $this->detected_at?->toIso8601String(),
            'anpd_reported_at' => $this->anpd_reported_at?->toIso8601String(),
            'status' => $this->status,
            'reported_by' => $this->reported_by,
            'reporter' => $this->whenLoaded('reporter'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
