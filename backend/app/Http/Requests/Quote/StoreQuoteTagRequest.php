<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => $this->color === '' ? null : $this->color,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ];
    }
}
