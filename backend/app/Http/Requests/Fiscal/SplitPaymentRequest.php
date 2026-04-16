<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class SplitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'payments' => 'required|array|min:1',
            'payments.*.forma_pagamento' => 'required|string|max:100',
            'payments.*.valor' => 'required|numeric|min:0.01',
        ];
    }
}
