<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'recurring_contract_id' => ['nullable', 'integer', Rule::exists('recurring_contracts', 'id')->where('tenant_id', $tenantId)],
            'equipment_id' => ['nullable', 'integer', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'origin_type' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'has_schedule' => ['nullable', 'boolean'],
            'scheduled_from' => ['nullable', 'date'],
            'scheduled_to' => ['nullable', 'date', 'after_or_equal:scheduled_from'],
            'pending_invoice' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
