<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class RevokePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.permission.manage');
    }

    public function rules(): array
    {
        return [
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required' => 'Informe ao menos uma permissão.',
            'permissions.*.exists' => 'Uma ou mais permissões são inválidas.',
        ];
    }
}
