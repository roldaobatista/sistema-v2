<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJourneyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'regime_type' => 'sometimes|string|in:clt_mensal,clt_6meses,cct_anual',
            'daily_hours_limit' => 'sometimes|integer|min:60|max:1440',
            'weekly_hours_limit' => 'sometimes|integer|min:60|max:10080',
            'monthly_hours_limit' => 'nullable|integer|min:60',
            'break_minutes' => 'sometimes|integer|min:0|max:240',
            'displacement_counts_as_work' => 'sometimes|boolean',
            'wait_time_counts_as_work' => 'sometimes|boolean',
            'travel_meal_counts_as_break' => 'sometimes|boolean',
            'auto_suggest_clock_on_displacement' => 'sometimes|boolean',
            'pre_assigned_break' => 'sometimes|boolean',
            'overnight_min_hours' => 'sometimes|integer|min:1|max:24',
            'oncall_multiplier_percent' => 'sometimes|integer|min:0|max:100',
            'overtime_50_percent_limit' => 'nullable|integer|min:0',
            'overtime_100_percent_limit' => 'nullable|integer|min:0',
            'saturday_is_overtime' => 'sometimes|boolean',
            'sunday_is_overtime' => 'sometimes|boolean',
            'custom_rules' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
