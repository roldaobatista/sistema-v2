<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('custom_description') && $this->input('custom_description') === '') {
            $this->merge(['custom_description' => null]);
        }
        if ($this->has('discount_percentage') && $this->input('discount_percentage') === '') {
            $this->merge(['discount_percentage' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'custom_description' => 'nullable|string',
            'quantity' => 'sometimes|numeric|min:0.01',
            'original_price' => 'sometimes|numeric|min:0',
            'unit_price' => 'sometimes|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
