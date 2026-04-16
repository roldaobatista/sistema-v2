<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJourneyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.journey.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('night_shift_pct') && $this->input('night_shift_pct') === '') {
            $this->merge(['night_shift_pct' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'daily_hours' => 'sometimes|numeric|min:1|max:24',
            'weekly_hours' => 'sometimes|numeric|min:1|max:168',
            'overtime_weekday_pct' => 'sometimes|integer|min:0|max:200',
            'overtime_weekend_pct' => 'sometimes|integer|min:0|max:200',
            'overtime_holiday_pct' => 'sometimes|integer|min:0|max:200',
            'night_shift_pct' => 'nullable|integer|min:0|max:100',
            'uses_hour_bank' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
