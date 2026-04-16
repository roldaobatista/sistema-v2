<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementUncertaintyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.reading.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('calibration_id') && $this->input('calibration_id') === '') {
            $cleaned['calibration_id'] = null;
        }
        if ($this->has('coverage_factor') && $this->input('coverage_factor') === '') {
            $cleaned['coverage_factor'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'equipment_id' => 'required|integer',
            'calibration_id' => 'nullable|integer',
            'measurement_type' => 'required|string',
            'nominal_value' => 'required|numeric',
            'measured_values' => 'required|array|min:3',
            'measured_values.*' => 'numeric',
            'unit' => 'required|string|max:20',
            'coverage_factor' => 'nullable|numeric|min:1|max:4',
        ];
    }
}
