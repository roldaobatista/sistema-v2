<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class MatchBankEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create') || $this->user()->can('finance.payable.create');
    }

    public function rules(): array
    {
        return [
            'matched_type' => ['required', 'string', 'max:120'],
            'matched_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
