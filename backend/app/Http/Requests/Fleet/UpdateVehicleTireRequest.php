<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleTireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['serial_number', 'brand', 'model', 'tread_depth', 'retread_count', 'installed_at', 'installed_km'];
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
            'serial_number' => 'nullable|string',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'position' => 'sometimes|string',
            'tread_depth' => 'nullable|numeric',
            'retread_count' => 'nullable|integer',
            'installed_at' => 'nullable|date',
            'installed_km' => 'nullable|integer',
            'status' => ['sometimes', Rule::in(['active', 'retired', 'warehouse'])],
        ];
    }
}
