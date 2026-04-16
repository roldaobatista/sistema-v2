<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleInsuranceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['policy_number', 'deductible_value', 'broker_name', 'broker_phone', 'notes'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'insurer' => 'sometimes|string|max:150',
            'policy_number' => 'nullable|string|max:80',
            'coverage_type' => ['sometimes', Rule::in(['comprehensive', 'third_party', 'total_loss'])],
            'premium_value' => 'sometimes|numeric|min:0',
            'deductible_value' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'broker_name' => 'nullable|string|max:150',
            'broker_phone' => 'nullable|string|max:30',
            'status' => ['sometimes', Rule::in(['active', 'expired', 'cancelled', 'pending'])],
            'notes' => 'nullable|string',
        ];
    }
}
