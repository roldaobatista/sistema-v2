<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreJourneyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.journey.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['night_shift_pct', 'night_start', 'night_end', 'hour_bank_expiry_months'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }

        $defaults = [
            'night_shift_pct' => 20,
            'night_start' => '22:00',
            'night_end' => '05:00',
            'uses_hour_bank' => false,
            'hour_bank_expiry_months' => 6,
            'is_default' => false,
        ];

        $this->merge(array_filter(
            $defaults,
            fn (string $field) => ! $this->has($field),
            ARRAY_FILTER_USE_KEY
        ));
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'daily_hours' => 'required|numeric|min:1|max:24',
            'weekly_hours' => 'required|numeric|min:1|max:168',
            'overtime_weekday_pct' => 'required|integer|min:0|max:200',
            'overtime_weekend_pct' => 'required|integer|min:0|max:200',
            'overtime_holiday_pct' => 'required|integer|min:0|max:200',
            'night_shift_pct' => 'nullable|integer|min:0|max:100',
            'night_start' => 'nullable|date_format:H:i',
            'night_end' => 'nullable|date_format:H:i',
            'uses_hour_bank' => 'sometimes|boolean',
            'hour_bank_expiry_months' => 'nullable|integer|min:1|max:24',
            'is_default' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da regra é obrigatório.',
        ];
    }
}
