<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class RejectSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.settlement.approve');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'O motivo da rejeição é obrigatório.',
        ];
    }
}
