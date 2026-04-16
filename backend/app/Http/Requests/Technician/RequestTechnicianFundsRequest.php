<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class RequestTechnicianFundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.cashbox.request_funds') || $this->user()->can('technicians.cashbox.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && $this->input('reason') === '') {
            $this->merge(['reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
            'payment_method' => 'nullable|in:cash,corporate_card',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'O valor é obrigatório.',
            'amount.min' => 'O valor deve ser maior que zero.',
        ];
    }
}
