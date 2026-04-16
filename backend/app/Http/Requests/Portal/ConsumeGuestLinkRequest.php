<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsumeGuestLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — guest link token auth
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:approve,reject',
            'comments' => 'nullable|string|max:2000',
            'signer_name' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'A ação é obrigatória.',
            'action.in' => 'A ação deve ser approve ou reject.',
            'comments.max' => 'Os comentários não podem exceder 2000 caracteres.',
            'signer_name.max' => 'O nome do assinante não pode exceder 255 caracteres.',
        ];
    }
}
