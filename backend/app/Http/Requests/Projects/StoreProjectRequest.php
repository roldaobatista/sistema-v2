<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.project.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => "required|integer|exists:customers,id,tenant_id,{$tenantId}",
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:planning,active,on_hold,completed,cancelled',
            'priority' => 'required|string|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'billing_type' => 'required|string|in:milestone,hourly,fixed_price',
            'hourly_rate' => 'nullable|numeric|min:0',
            'crm_deal_id' => "nullable|integer|exists:crm_deals,id,tenant_id,{$tenantId}",
            'tags' => 'nullable|array',
            'manager_id' => "nullable|integer|exists:users,id,tenant_id,{$tenantId}",
        ];
    }
}
