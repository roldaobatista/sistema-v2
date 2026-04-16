<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class StoreJourneyPolicyRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'regime_type' => 'required|string|in:clt_mensal,clt_6meses,cct_anual',
            'daily_hours_limit' => 'required|integer|min:60|max:1440',
            'weekly_hours_limit' => 'required|integer|min:60|max:10080',
            'monthly_hours_limit' => 'nullable|integer|min:60',
            'break_minutes' => 'required|integer|min:0|max:240',
            'displacement_counts_as_work' => 'required|boolean',
            'wait_time_counts_as_work' => 'required|boolean',
            'travel_meal_counts_as_break' => 'required|boolean',
            'auto_suggest_clock_on_displacement' => 'required|boolean',
            'pre_assigned_break' => 'required|boolean',
            'overnight_min_hours' => 'required|integer|min:1|max:24',
            'oncall_multiplier_percent' => 'required|integer|min:0|max:100',
            'overtime_50_percent_limit' => 'nullable|integer|min:0',
            'overtime_100_percent_limit' => 'nullable|integer|min:0',
            'saturday_is_overtime' => 'required|boolean',
            'sunday_is_overtime' => 'required|boolean',
            'custom_rules' => 'nullable|array',
            'is_default' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }
}
