<?php

namespace App\Http\Requests\Financial;

use App\Models\FuelingLog;
use App\Models\Lookups\FuelingFuelType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFuelingLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.fueling_log.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['work_order_id', 'gas_station', 'notes'];
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
        $allowedFuelTypes = LookupValueResolver::allowedValues(
            FuelingFuelType::class,
            FuelingLog::FUEL_TYPES,
            $tenantId
        );

        return [
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'vehicle_plate' => 'sometimes|string|max:20',
            'odometer_km' => 'sometimes|numeric|min:0',
            'gas_station' => 'nullable|string|max:255',
            'fuel_type' => ['sometimes', Rule::in($allowedFuelTypes)],
            'liters' => 'sometimes|numeric|min:0.01',
            'price_per_liter' => 'sometimes|numeric|min:0.01',
            'total_amount' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'affects_technician_cash' => 'boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
