<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.inspection.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observations' => $this->observations === '' ? null : $this->observations,
            'status' => $this->status === '' ? null : $this->status,
        ]);
    }

    public function rules(): array
    {
        return [
            'inspection_date' => 'required|date',
            'odometer_km' => 'required|integer|min:0',
            'checklist_data' => 'nullable|array',
            'status' => 'nullable|in:ok,issues_found,critical',
            'observations' => 'nullable|string',
        ];
    }
}
