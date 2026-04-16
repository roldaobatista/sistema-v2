<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialCheckStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.payment.create') || $this->user()->can('finance.payable.update');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,deposited,compensated,returned,custody',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O status é obrigatório.',
        ];
    }
}
