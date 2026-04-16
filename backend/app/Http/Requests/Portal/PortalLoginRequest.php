<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — no auth required
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }
}
