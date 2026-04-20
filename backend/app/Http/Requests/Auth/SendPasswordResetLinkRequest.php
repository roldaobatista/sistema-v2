<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendPasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — no auth required
    }

    // sec-21 (Re-auditoria Camada 1 r3): normaliza email em lowercase antes
    // da validação. Consistente com LoginRequest — evita que "User@Ex.com" e
    // "user@ex.com" sejam tratados como contas distintas, e que o rate
    // limiter veja keys diferentes para mesmo usuário case-insensitive.
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}
