<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class ConvertQuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_installation_testing')) {
            return;
        }

        $value = $this->input('is_installation_testing');

        if (! is_string($value)) {
            return;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['true', '1'], true)) {
            $this->merge(['is_installation_testing' => true]);

            return;
        }

        if (in_array($normalized, ['false', '0'], true)) {
            $this->merge(['is_installation_testing' => false]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.convert');
    }

    public function rules(): array
    {
        return [
            'is_installation_testing' => ['nullable', 'boolean'],
        ];
    }
}
