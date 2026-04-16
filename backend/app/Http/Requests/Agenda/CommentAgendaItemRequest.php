<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class CommentAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    public function rules(): array
    {
        return [
            'body' => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'O conteúdo do comentário é obrigatório.',
        ];
    }
}
