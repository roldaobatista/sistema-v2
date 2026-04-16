<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class FinancialExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.view') || $this->user()->can('finance.payable.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('os_number') && $this->input('os_number') === '') {
            $this->merge(['os_number' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:receivable,payable',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'os_number' => 'nullable|string|max:30',
        ];
    }
}
