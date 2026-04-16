<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgendaTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.manage.rules');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['descricao', 'tipo', 'prioridade', 'visibilidade', 'categoria', 'due_days', 'subtasks', 'default_watchers', 'tags'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'nome' => 'sometimes|string|max:150',
            'descricao' => 'nullable|string|max:500',
            'tipo' => ['nullable', 'string'],
            'prioridade' => ['nullable', 'string'],
            'visibilidade' => ['nullable', 'string'],
            'categoria' => 'nullable|string|max:60',
            'due_days' => 'nullable|integer|min:0|max:365',
            'subtasks' => 'nullable|array',
            'default_watchers' => 'nullable|array',
            'tags' => 'nullable|array',
            'ativo' => 'sometimes|boolean',
        ];
    }
}
