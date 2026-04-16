<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class SyncDeniedPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.permission.manage');
    }

    public function rules(): array
    {
        return [
            'denied_permissions' => 'present|array',
            'denied_permissions.*' => 'string|exists:permissions,name',
        ];
    }

    public function messages(): array
    {
        return [
            'denied_permissions.*.exists' => 'Uma ou mais permissões são inválidas.',
        ];
    }
}
