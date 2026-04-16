<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.role.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'display_name' => $this->display_name === '' ? null : $this->display_name,
            'description' => $this->description === '' ? null : $this->description,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) app('current_tenant_id');
        $roleId = $this->route('role')?->id ?? 0;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('roles')->ignore($roleId)->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'display_name' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }
}
