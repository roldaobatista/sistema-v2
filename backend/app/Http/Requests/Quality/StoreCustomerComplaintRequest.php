<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.complaint.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['work_order_id', 'equipment_id', 'category', 'severity', 'assigned_to', 'response_due_at'];
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
        $tenantId = $this->user()->current_tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'description' => 'required|string',
            'category' => 'nullable|in:service,certificate,delay,billing,other',
            'severity' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'response_due_at' => 'nullable|date',
        ];
    }
}
