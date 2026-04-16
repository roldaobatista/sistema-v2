<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.inspection.create') || $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('observations') && $this->input('observations') === '') {
            $this->merge(['observations' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'fleet_vehicle_id' => ['required', Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'inspection_date' => 'required|date',
            'odometer_km' => 'required|integer',
            'checklist_data' => 'required|array',
            'status' => ['required', Rule::in(['ok', 'issues_found', 'critical'])],
            'observations' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
