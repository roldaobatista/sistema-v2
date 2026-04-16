<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
