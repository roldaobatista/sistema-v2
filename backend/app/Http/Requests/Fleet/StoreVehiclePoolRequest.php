<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiclePoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.management');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('purpose') && $this->input('purpose') === '') {
            $this->merge(['purpose' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'fleet_vehicle_id' => ['nullable', Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'requested_start' => 'required|date',
            'requested_end' => 'required|date|after:requested_start',
            'purpose' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
