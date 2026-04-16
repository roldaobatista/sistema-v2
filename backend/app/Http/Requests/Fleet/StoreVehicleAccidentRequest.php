<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleAccidentRequest extends FormRequest
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'fleet_vehicle_id' => [
                'required',
                Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'occurrence_date' => 'required|date',
            'location' => 'nullable|string',
            'description' => 'required|string',
            'third_party_involved' => 'boolean',
            'third_party_info' => 'nullable|string',
            'police_report_number' => 'nullable|string',
            'photos' => 'nullable|array',
            'estimated_cost' => 'nullable|numeric',
            'status' => ['required', Rule::in(['investigating', 'insurance_claim', 'repaired', 'loss'])],
        ];
    }
}
