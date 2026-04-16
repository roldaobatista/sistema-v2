<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class SearchBankReconciliationFinancialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.view') || $this->user()->can('finance.payable.view');
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', 'string', 'in:receivable,payable'],
        ];
    }
}
