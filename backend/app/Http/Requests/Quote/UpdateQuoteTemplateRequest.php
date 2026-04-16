<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteTemplateRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'warranty_terms' => 'nullable|string',
            'payment_terms_text' => 'nullable|string',
            'general_conditions' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }
}
