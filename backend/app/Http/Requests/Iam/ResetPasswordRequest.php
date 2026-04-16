<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.user.update');
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }
}
