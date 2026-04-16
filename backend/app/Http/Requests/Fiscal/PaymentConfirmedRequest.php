<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class PaymentConfirmedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer',
            'amount' => 'required|numeric',
            'transaction_id' => 'required|string|max:255',
        ];
    }
}
