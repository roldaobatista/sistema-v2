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

    // sec-21 (Re-auditoria Camada 1 r3): normaliza email em lowercase.
    // Token de reset é emitido para email já normalizado; o reset precisa
    // bater pela mesma normalização.
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        // sec-17 (Re-auditoria Camada 1 r3): delega para Password::defaults()
        // centralizado em AppServiceProvider — garante que a política de senha
        // (12+ chars, mixed case, letters, numbers, symbols, uncompromised) é
        // aplicada consistentemente em todos os fluxos que criam/alteram senha,
        // sem regra local divergente.
        return [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ];
    }
}
