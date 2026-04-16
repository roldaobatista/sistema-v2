<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'expiry_days' => $this->expiry_days === '' ? null : $this->expiry_days,
        ]);
    }

    public function rules(): array
    {
        return [
            'min_length' => 'required|integer|min:6|max:32',
            'require_uppercase' => 'boolean',
            'require_lowercase' => 'boolean',
            'require_number' => 'boolean',
            'require_special' => 'boolean',
            'expiry_days' => 'nullable|integer|min:0|max:365',
            'max_attempts' => 'integer|min:3|max:20',
            'lockout_minutes' => 'integer|min:1|max:1440',
            'history_count' => 'integer|min:0|max:10',
        ];
    }
}
