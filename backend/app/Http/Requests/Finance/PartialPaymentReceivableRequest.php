<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class PartialPaymentReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payment_method') && $this->input('payment_method') === '') {
            $this->merge(['payment_method' => null]);
        }
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
