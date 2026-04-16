<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.maintenance.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['parts_replaced', 'cost', 'downtime_hours', 'work_order_id', 'next_maintenance_at'] as $field) {
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'equipment_id' => ['required', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'type' => 'required|in:preventiva,corretiva,ajuste,limpeza',
            'description' => 'required|string',
            'parts_replaced' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'downtime_hours' => 'nullable|numeric|min:0',
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'next_maintenance_at' => 'nullable|date',
        ];
    }
}
