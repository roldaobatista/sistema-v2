<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class RejectQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.approve');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && $this->input('reason') === '') {
            $this->merge(['reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
