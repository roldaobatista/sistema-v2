<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.user.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['phone', 'branch_id'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
            'roles' => 'array',
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))],
            'is_active' => 'boolean',
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.required' => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
