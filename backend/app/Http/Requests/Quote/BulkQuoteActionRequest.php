<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;

class BulkQuoteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:delete,approve,send,export'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'Selecione pelo menos um orçamento.',
            'ids.max' => 'Máximo de 50 orçamentos por operação.',
            'action.in' => 'Ação inválida. Permitidas: delete, approve, send, export.',
        ];
    }
}
