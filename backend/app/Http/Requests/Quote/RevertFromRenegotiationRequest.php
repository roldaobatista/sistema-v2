<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class RevertFromRenegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.convert');
    }

    public function rules(): array
    {
        return [
            'target_status' => ['required', 'in:draft,internally_approved'],
        ];
    }
}
