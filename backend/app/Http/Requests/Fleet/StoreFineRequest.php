<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.fine.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'driver_id' => $this->driver_id === '' ? null : $this->driver_id,
            'infraction_code' => $this->infraction_code === '' ? null : $this->infraction_code,
            'description' => $this->description === '' ? null : $this->description,
            'points' => $this->points === '' ? null : $this->points,
            'due_date' => $this->due_date === '' ? null : $this->due_date,
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
            'driver_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'fine_date' => 'required|date',
            'infraction_code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'points' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
        ];
    }
}
