<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'warranty_terms',
            'payment_terms_text',
            'general_conditions',
            'delivery_terms',
            'is_default',
            'is_active',
        ];
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
            'name' => 'required|string|max:255',
            'warranty_terms' => 'nullable|string',
            'payment_terms_text' => 'nullable|string',
            'general_conditions' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do template é obrigatório.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
        ];
    }
}
