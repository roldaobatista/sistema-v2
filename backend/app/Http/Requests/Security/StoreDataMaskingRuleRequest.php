<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class StoreDataMaskingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.create');
    }

    public function rules(): array
    {
        return [
            'table_name' => 'required|string|max:100',
            'column_name' => 'required|string|max:100',
            'masking_type' => 'required|in:full,partial,email,phone,cpf,cnpj',
            'roles_exempt' => 'nullable|array',
        ];
    }
}
