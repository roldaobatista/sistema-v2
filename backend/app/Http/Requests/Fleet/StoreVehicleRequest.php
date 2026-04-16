<?php

namespace App\Http\Requests\Fleet;

use App\Models\Lookups\FleetFuelType;
use App\Models\Lookups\FleetVehicleStatus;
use App\Models\Lookups\FleetVehicleType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.vehicle.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['brand', 'model', 'year', 'color', 'type', 'fuel_type', 'odometer_km', 'renavam', 'chassis', 'crlv_expiry', 'insurance_expiry', 'next_maintenance', 'purchase_value', 'assigned_user_id', 'status', 'notes'];
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $allowedVehicleTypes = LookupValueResolver::allowedValues(FleetVehicleType::class, [
            'car' => 'Carro', 'truck' => 'Caminhao', 'motorcycle' => 'Motocicleta', 'van' => 'Van',
        ], $tenantId);
        $allowedFuelTypes = LookupValueResolver::allowedValues(FleetFuelType::class, [
            'flex' => 'Flex', 'diesel' => 'Diesel', 'gasoline' => 'Gasolina', 'electric' => 'Eletrico', 'ethanol' => 'Etanol',
        ], $tenantId);
        $allowedStatuses = LookupValueResolver::allowedValues(FleetVehicleStatus::class, [
            'active' => 'Ativo', 'maintenance' => 'Manutencao', 'inactive' => 'Inativo',
        ], $tenantId);

        return [
            'plate' => 'required|string|max:10',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'color' => 'nullable|string|max:50',
            'type' => ['nullable', Rule::in($allowedVehicleTypes)],
            'fuel_type' => ['nullable', Rule::in($allowedFuelTypes)],
            'odometer_km' => 'nullable|integer|min:0',
            'renavam' => 'nullable|string|max:20',
            'chassis' => 'nullable|string|max:30',
            'crlv_expiry' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'next_maintenance' => 'nullable|date',
            'purchase_value' => 'nullable|numeric|min:0',
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', Rule::in($allowedStatuses)],
            'notes' => 'nullable|string',
        ];
    }
}
