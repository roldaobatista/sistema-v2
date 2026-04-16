<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.tool_inventory.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['serial_number', 'category', 'assigned_to', 'fleet_vehicle_id', 'calibration_due', 'status', 'value', 'notes'];
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

        return [
            'name' => 'sometimes|string|max:255',
            'serial_number' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'fleet_vehicle_id' => [
                'nullable',
                Rule::exists('fleet_vehicles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'calibration_due' => 'nullable|date',
            'status' => 'nullable|in:available,in_use,maintenance,retired',
            'value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
