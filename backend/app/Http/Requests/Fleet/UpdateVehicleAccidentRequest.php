<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleAccidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'third_party_involved' => $this->boolean('third_party_involved'),
        ]);
    }

    public function rules(): array
    {
        return [
            'occurrence_date' => 'sometimes|date',
            'location' => 'nullable|string',
            'description' => 'sometimes|string',
            'third_party_involved' => 'boolean',
            'third_party_info' => 'nullable|string',
            'police_report_number' => 'nullable|string',
            'photos' => 'nullable|array',
            'estimated_cost' => 'nullable|numeric',
            'status' => ['sometimes', Rule::in(['investigating', 'insurance_claim', 'repaired', 'loss'])],
        ];
    }
}
