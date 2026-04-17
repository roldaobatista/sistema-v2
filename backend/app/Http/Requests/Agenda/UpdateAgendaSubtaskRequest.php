<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgendaSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'concluido' => 'sometimes|boolean',
            'ordem' => 'sometimes|integer|min:0',
        ];
    }
}
