<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'title' => 'required|string|max:255',
            'objective' => 'nullable|string',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'revenue_target' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'actions' => 'nullable|array',
            'actions.*.title' => 'required|string|max:255',
            'actions.*.description' => 'nullable|string',
            'actions.*.due_date' => 'nullable|date',
            'actions.*.assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
