<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class PayAccountReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.settle');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
