<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('observations') && $this->input('observations') === '') {
            $this->merge(['observations' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'inspection_date' => 'sometimes|date',
            'odometer_km' => 'sometimes|integer',
            'checklist_data' => 'sometimes|array',
            'status' => ['sometimes', Rule::in(['ok', 'issues_found', 'critical'])],
            'observations' => 'nullable|string',
        ];
    }
}
