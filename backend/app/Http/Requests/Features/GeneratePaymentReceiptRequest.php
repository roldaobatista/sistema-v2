<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payment.create');
    }

    public function rules(): array
    {
        return [];
    }
}
