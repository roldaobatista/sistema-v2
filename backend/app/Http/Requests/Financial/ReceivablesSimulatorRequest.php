<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class ReceivablesSimulatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.view') || $this->user()->can('finance.receivable.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('min_amount') && $this->input('min_amount') === '') {
            $this->merge(['min_amount' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'monthly_rate' => 'required|numeric|min:0|max:10',
            'min_amount' => 'nullable|numeric|min:0',
        ];
    }
}
