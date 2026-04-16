<?php

namespace App\Http\Requests\Financial;

use App\Models\FuelingLog;
use App\Models\Lookups\FuelingFuelType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuelingLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.fueling_log.create');
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
            'vehicle_plate' => 'required|string|max:20',
            'odometer_km' => 'required|numeric|min:0',
            'gas_station' => 'nullable|string|max:255',
            'fuel_type' => ['required', Rule::in($allowedFuelTypes)],
            'liters' => 'required|numeric|min:0.01',
            'price_per_liter' => 'required|numeric|min:0.01',
            'total_amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'affects_technician_cash' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $liters = (string) ($this->input('liters') ?? '0');
            $pricePerLiter = (string) ($this->input('price_per_liter') ?? '0');
            $totalAmount = (string) ($this->input('total_amount') ?? '0');
            $expected = bcmul($liters, $pricePerLiter, 2);
            $diff = bcsub($totalAmount, $expected, 2);
            if (bccomp($diff, '0.02', 2) > 0 || bccomp($diff, '-0.02', 2) < 0) {
                $validator->errors()->add('total_amount', 'O valor total não confere com litros × preço por litro.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'vehicle_plate.required' => 'A placa do veículo é obrigatória.',
            'fuel_type.required' => 'O tipo de combustível é obrigatório.',
            'liters.required' => 'A quantidade em litros é obrigatória.',
            'date.required' => 'A data do abastecimento é obrigatória.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
