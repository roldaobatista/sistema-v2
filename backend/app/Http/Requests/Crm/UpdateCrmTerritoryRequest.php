<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmTerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.territory.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'regions' => 'nullable|array',
            'zip_code_ranges' => 'nullable|array',
            'manager_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'is_active' => 'boolean',
            'member_ids' => 'nullable|array',
            'member_ids.*' => [Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
