<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNonConformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.reading.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['equipment_id', 'work_order_id', 'corrective_action', 'responsible_id', 'deadline'];
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|string|in:equipment,process,service,product',
            'severity' => 'required|string|in:minor,major,critical',
            'equipment_id' => ['nullable', 'integer', Rule::exists('equipment', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'corrective_action' => 'nullable|string',
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'deadline' => 'nullable|date',
        ];
    }
}
