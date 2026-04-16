<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class ApproveQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.approve');
    }

    public function rules(): array
    {
        return [
            'approval_channel' => ['required', 'string', 'in:whatsapp,email,phone,in_person,portal,integration,other'],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'approval_channel.required' => 'O canal de aprovação é obrigatório.',
            'approval_channel.in' => 'O canal de aprovação informado é inválido.',
            'terms_accepted.required' => 'É obrigatório confirmar o aceite do cliente.',
            'terms_accepted.accepted' => 'É obrigatório confirmar o aceite do cliente.',
        ];
    }
}
