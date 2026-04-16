<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.time_entry.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'project_resource_id' => "required|integer|exists:project_resources,id,tenant_id,{$tenantId}",
            'milestone_id' => "nullable|integer|exists:project_milestones,id,tenant_id,{$tenantId}",
            'work_order_id' => "nullable|integer|exists:work_orders,id,tenant_id,{$tenantId}",
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0.25|max:24',
            'description' => 'nullable|string',
            'billable' => 'required|boolean',
        ];
    }
}
