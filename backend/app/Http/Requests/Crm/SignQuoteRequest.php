<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class SignQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'signer_name' => 'required|string|max:255',
            'signature_data' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'signer_name.required' => 'O nome do assinante é obrigatório.',
            'signature_data.required' => 'A assinatura é obrigatória.',
        ];
    }
}
