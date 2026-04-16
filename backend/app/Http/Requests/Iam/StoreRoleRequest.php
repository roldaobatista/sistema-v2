<?php

namespace App\Http\Requests\Iam;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.role.create');
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
        $tenantId = app('current_tenant_id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::notIn([Role::SUPER_ADMIN, Role::ADMIN]),
                Rule::unique('roles')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'display_name' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }
}
