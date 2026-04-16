<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaTemplateRequest extends FormRequest
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'nome' => 'required|string|max:150',
            'descricao' => 'nullable|string|max:500',
            'tipo' => ['nullable', 'string', Rule::in(['task', 'reminder'])],
            'prioridade' => ['nullable', 'string'],
            'visibilidade' => ['nullable', 'string'],
            'categoria' => 'nullable|string|max:60',
            'due_days' => 'nullable|integer|min:0|max:365',
            'subtasks' => 'nullable|array',
            'subtasks.*' => 'string|max:255',
            'default_watchers' => 'nullable|array',
            'default_watchers.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'tags' => 'nullable|array',
        ];
    }
}
