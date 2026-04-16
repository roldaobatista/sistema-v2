<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class ToggleRolePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.permission.manage');
    }

    public function rules(): array
    {
        return [
            'role_id' => 'required|integer|exists:roles,id',
            'permission_id' => 'required|integer|exists:permissions,id',
        ];
    }
}
