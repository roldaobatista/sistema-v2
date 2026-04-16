<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class DealsConvertToQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.deal.update')
            || (bool) $this->user()?->can('quotes.quote.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
