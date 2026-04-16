<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class SyncDirectPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.permission.manage');
    }

    public function rules(): array
    {
        return [
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.*.exists' => 'Uma ou mais permissões são inválidas.',
        ];
    }
}
