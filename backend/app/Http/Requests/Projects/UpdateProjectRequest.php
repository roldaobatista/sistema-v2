<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.project.update');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => "sometimes|integer|exists:customers,id,tenant_id,{$tenantId}",
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:planning,active,on_hold,completed,cancelled',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'billing_type' => 'sometimes|string|in:milestone,hourly,fixed_price',
            'hourly_rate' => 'nullable|numeric|min:0',
            'crm_deal_id' => "nullable|integer|exists:crm_deals,id,tenant_id,{$tenantId}",
            'tags' => 'nullable|array',
            'manager_id' => "nullable|integer|exists:users,id,tenant_id,{$tenantId}",
        ];
    }
}
