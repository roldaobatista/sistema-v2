<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.payment.create') || $this->user()->can('finance.payable.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('notes') && $this->input('notes') === '') {
            $cleaned['notes'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:received,issued',
            'number' => 'required|string|max:50',
            'bank' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date',
            'issuer' => 'required|string|max:255',
            'status' => 'nullable|in:pending,deposited,compensated,returned,custody',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo do cheque é obrigatório.',
            'number.required' => 'O número do cheque é obrigatório.',
        ];
    }
}
