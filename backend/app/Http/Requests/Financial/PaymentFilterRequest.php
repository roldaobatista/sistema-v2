<?php

namespace App\Http\Requests\Financial;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.view') || $this->user()->can('finance.payable.view');
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:20'],
            'payable_type' => ['nullable', Rule::in([AccountReceivable::class, AccountPayable::class])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
