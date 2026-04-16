<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class MarkSettlementPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.settlement.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('payment_notes') && $this->input('payment_notes') === '') {
            $cleaned['payment_notes'] = null;
        }
        if ($this->has('payment_method') && $this->input('payment_method') === '') {
            $cleaned['payment_method'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:30',
            'payment_notes' => 'nullable|string|max:1000',
        ];
    }
}
