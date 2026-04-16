<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehiclePoolStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'status' => ['required', Rule::in(['approved', 'rejected', 'in_use', 'completed', 'cancelled'])],
            'actual_start' => 'nullable|date',
            'actual_end' => 'nullable|date',
            'fleet_vehicle_id' => ['nullable', Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
