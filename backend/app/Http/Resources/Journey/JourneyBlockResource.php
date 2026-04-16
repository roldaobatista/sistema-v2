<?php

namespace App\Http\Resources\Journey;

use App\Models\JourneyBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JourneyBlock
 */
class JourneyBlockResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journey_day_id' => $this->journey_day_id,
            'user_id' => $this->user_id,
            'classification' => $this->classification->value,
            'classification_label' => $this->classification->label(),
            'is_work_time' => $this->classification->isWorkTime(),
            'is_paid_time' => $this->classification->isPaidTime(),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'work_order_id' => $this->work_order_id,
            'time_clock_entry_id' => $this->time_clock_entry_id,
            'fleet_trip_id' => $this->fleet_trip_id,
            'schedule_id' => $this->schedule_id,
            'source' => $this->source,
            'is_auto_classified' => $this->is_auto_classified,
            'is_manually_adjusted' => $this->is_manually_adjusted,
            'adjusted_by' => $this->adjusted_by,
            'adjustment_reason' => $this->adjustment_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
