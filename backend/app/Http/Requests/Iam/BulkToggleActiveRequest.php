<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkToggleActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.user.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Informe ao menos um usuário.',
            'user_ids.min' => 'Informe ao menos um usuário.',
            'is_active.required' => 'Informe se ativa ou desativa.',
        ];
    }
}
