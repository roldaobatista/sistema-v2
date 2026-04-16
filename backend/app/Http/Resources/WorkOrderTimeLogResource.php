<?php

namespace App\Http\Resources;

use App\Models\WorkOrderTimeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderTimeLog
 */
class WorkOrderTimeLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'work_order_id' => $this->work_order_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user'),
            'activity_type' => $this->activity_type,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
