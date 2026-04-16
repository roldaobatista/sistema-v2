<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Portal authenticated users — auth checked in controller via portalUser()
        return true; // Public endpoint — portal token auth
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signer_name' => 'required|string|max:255',
            'signature_data' => 'required|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signer_name.required' => 'O nome do assinante é obrigatório.',
            'signer_name.max' => 'O nome do assinante não pode exceder 255 caracteres.',
            'signature_data.required' => 'A assinatura é obrigatória.',
        ];
    }
}
