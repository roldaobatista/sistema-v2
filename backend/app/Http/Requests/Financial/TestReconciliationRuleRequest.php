<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class TestReconciliationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.view') || $this->user()->can('finance.payable.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'match_value' => $this->match_value === '' ? null : $this->match_value,
            'match_amount_min' => $this->match_amount_min === '' ? null : $this->match_amount_min,
            'match_amount_max' => $this->match_amount_max === '' ? null : $this->match_amount_max,
        ]);
    }

    public function rules(): array
    {
        return [
            'match_field' => 'required|in:description,amount,cnpj,combined',
            'match_operator' => 'required|in:contains,equals,regex,between',
            'match_value' => 'nullable|string|max:500',
            'match_amount_min' => 'nullable|numeric|min:0',
            'match_amount_max' => 'nullable|numeric|min:0',
        ];
    }
}
