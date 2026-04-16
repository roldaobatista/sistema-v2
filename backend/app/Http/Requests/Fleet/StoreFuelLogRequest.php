<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuelLogRequest extends FormRequest
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
        $tenantId = $this->tenantId();

        return [
            'fleet_vehicle_id' => ['required', Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'date' => 'required|date',
            'odometer_km' => 'required|integer',
            'liters' => 'required|numeric',
            'price_per_liter' => 'required|numeric',
            'total_value' => 'required|numeric',
            'fuel_type' => 'nullable|string',
            'gas_station' => 'nullable|string',
            'receipt_path' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
