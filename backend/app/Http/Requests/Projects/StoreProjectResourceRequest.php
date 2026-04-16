<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.resource.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => "required|integer|exists:users,id,tenant_id,{$tenantId}",
            'role' => 'required|string|max:100',
            'allocation_percent' => 'required|numeric|min:1|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'hourly_rate' => 'nullable|numeric|min:0',
            'total_hours_planned' => 'nullable|numeric|min:0',
        ];
    }
}
