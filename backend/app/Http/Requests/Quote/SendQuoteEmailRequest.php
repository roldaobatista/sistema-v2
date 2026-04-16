<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class SendQuoteEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.send');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['recipient_name', 'message'];
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
        return [
            'recipient_email' => 'required|email',
            'recipient_name' => 'nullable|string|max:255',
            'message' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_email.required' => 'O e-mail do destinatário é obrigatório.',
            'recipient_email.email' => 'Informe um e-mail válido.',
        ];
    }
}
