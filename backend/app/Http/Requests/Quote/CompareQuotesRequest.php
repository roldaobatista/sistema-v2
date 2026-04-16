<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class CompareQuotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.view');
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:2|max:5',
            'ids.*' => 'integer',
        ];
    }
}
