<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleTireRequest extends FormRequest
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
        $tenantId = $this->tenantId();

        return [
            'fleet_vehicle_id' => ['required', Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'serial_number' => 'nullable|string',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'position' => 'required|string',
            'tread_depth' => 'nullable|numeric',
            'retread_count' => 'nullable|integer',
            'installed_at' => 'nullable|date',
            'installed_km' => 'nullable|integer',
            'status' => ['required', Rule::in(['active', 'retired', 'warehouse'])],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
