<?php

namespace App\Http\Resources\Journey;

use App\Models\JourneyPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JourneyPolicy
 */
class JourneyPolicyResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'regime_type' => $this->regime_type,
            'daily_hours_limit' => $this->daily_hours_limit,
            'weekly_hours_limit' => $this->weekly_hours_limit,
            'monthly_hours_limit' => $this->monthly_hours_limit,
            'break_minutes' => $this->break_minutes,
            'displacement_counts_as_work' => $this->displacement_counts_as_work,
            'wait_time_counts_as_work' => $this->wait_time_counts_as_work,
            'travel_meal_counts_as_break' => $this->travel_meal_counts_as_break,
            'auto_suggest_clock_on_displacement' => $this->auto_suggest_clock_on_displacement,
            'pre_assigned_break' => $this->pre_assigned_break,
            'overnight_min_hours' => $this->overnight_min_hours,
            'oncall_multiplier_percent' => $this->oncall_multiplier_percent,
            'overtime_50_percent_limit' => $this->overtime_50_percent_limit,
            'overtime_100_percent_limit' => $this->overtime_100_percent_limit,
            'saturday_is_overtime' => $this->saturday_is_overtime,
            'sunday_is_overtime' => $this->sunday_is_overtime,
            'custom_rules' => $this->custom_rules,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
