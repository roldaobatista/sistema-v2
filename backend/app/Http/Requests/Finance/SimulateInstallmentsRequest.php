<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class SimulateInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('interest_rate') && $this->input('interest_rate') === '') {
            $this->merge(['interest_rate' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'total_amount' => 'required|numeric|min:0.01',
            'installments' => 'required|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0',
            'first_due_date' => 'required|date|after:today',
        ];
    }
}
