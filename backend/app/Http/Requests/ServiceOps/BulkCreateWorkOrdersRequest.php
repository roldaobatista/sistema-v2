<?php

namespace App\Http\Requests\ServiceOps;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCreateWorkOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;

        return [
            'template' => 'required|array',
            // Only allow safe template fields — block tenant_id, created_by, status overrides
            'template.customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'template.description' => 'nullable|string|max:5000',
            'template.priority' => 'nullable|string|in:low,normal,high,urgent',
            'template.service_type' => 'nullable|string|max:100',
            'template.internal_notes' => 'nullable|string|max:5000',
            'template.assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('current_tenant_id', $tenantId)],
            'template.checklist_id' => 'nullable|integer',
            'template.sla_policy_id' => 'nullable|integer',
            'template.branch_id' => 'nullable|integer',
            'equipment_ids' => 'required|array|min:1',
            'equipment_ids.*' => ['integer', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
