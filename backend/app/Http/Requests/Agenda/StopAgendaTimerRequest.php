<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class StopAgendaTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.update');
    }

    public function rules(): array
    {
        return [
            'descricao' => 'nullable|string|max:1000',
        ];
    }
}
