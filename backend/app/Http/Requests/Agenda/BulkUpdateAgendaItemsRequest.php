<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateAgendaItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.close.self');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('value') && $this->input('value') === '') {
            $this->merge(['value' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
            'action' => 'required|string|in:complete,cancel,set_status,set_priority,assign',
            'value' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'É necessário informar ao menos um ID.',
            'action.required' => 'A ação é obrigatória.',
            'action.in' => 'Ação inválida.',
        ];
    }
}
