<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — no auth required
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }
}
