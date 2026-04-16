<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class TaxCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.view') || $this->user()->can('finance.dre.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tax_regime') && $this->input('tax_regime') === '') {
            $this->merge(['tax_regime' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'gross_amount' => 'required|numeric|min:0.01',
            'service_type' => 'required|string',
            'tax_regime' => 'nullable|in:simples_nacional,lucro_presumido,lucro_real',
        ];
    }
}
