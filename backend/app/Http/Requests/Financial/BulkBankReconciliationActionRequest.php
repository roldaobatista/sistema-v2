<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class BulkBankReconciliationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create') || $this->user()->can('finance.payable.create');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:auto-match,ignore,unmatch'],
            'entry_ids' => ['required', 'array', 'min:1', 'max:200'],
            'entry_ids.*' => ['integer', 'min:1'],
        ];
    }
}
