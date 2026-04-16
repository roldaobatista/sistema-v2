<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ordem') && $this->input('ordem') === '') {
            $this->merge(['ordem' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'ordem' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'O título da subtarefa é obrigatório.',
        ];
    }
}
