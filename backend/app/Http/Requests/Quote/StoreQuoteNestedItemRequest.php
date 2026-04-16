<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteNestedItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && is_string($this->input('description'))) {
            $this->merge([
                'description' => trim((string) $this->input('description')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descricao do item e obrigatoria.',
            'quantity.required' => 'A quantidade e obrigatoria.',
            'unit_price.required' => 'O preco unitario e obrigatorio.',
        ];
    }
}
