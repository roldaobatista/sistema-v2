<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.user.update');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'roles' => 'required|array',
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Informe ao menos um perfil.',
            'roles.*.exists' => 'Um ou mais perfis são inválidos.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
