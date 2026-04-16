<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['fuel_type', 'gas_station', 'receipt_path'];
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
            'date' => 'sometimes|date',
            'odometer_km' => 'sometimes|integer',
            'liters' => 'sometimes|numeric',
            'price_per_liter' => 'sometimes|numeric',
            'total_value' => 'sometimes|numeric',
            'fuel_type' => 'nullable|string',
            'gas_station' => 'nullable|string',
            'receipt_path' => 'nullable|string',
        ];
    }
}
