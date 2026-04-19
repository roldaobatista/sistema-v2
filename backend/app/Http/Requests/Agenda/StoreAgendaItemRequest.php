<?php

namespace App\Http\Requests\Agenda;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.create.task');
    }

    protected function prepareForValidation(): void
    {
        $aliases = [];

        if (! $this->has('due_at') && $this->filled('data_hora')) {
            $aliases['due_at'] = $this->input('data_hora');
        }

        if ($this->input('type') === 'reuniao') {
            $aliases['type'] = AgendaItemType::LEMBRETE->value;
        }

        $nullable = ['short_description', 'assignee_user_id', 'priority', 'visibility', 'due_at', 'remind_at', 'context', 'tags', 'watchers', 'visibility_users', 'visibility_departments'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        $payload = array_merge($aliases, $cleaned);

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $tipos = array_map(fn ($c) => $c->value, AgendaItemType::cases());
        $prioridades = array_map(fn ($c) => $c->value, AgendaItemPriority::cases());
        $visibilidades = array_map(fn ($c) => $c->value, AgendaItemVisibility::cases());
        $visibilidadesLower = array_map('strtolower', $visibilidades);

        return [
            'type' => ['required', 'string', Rule::in(array_merge($tipos, array_map('strtolower', $tipos)))],
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string|max:255',
            'assignee_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'priority' => ['nullable', 'string', Rule::in(array_merge($prioridades, array_map('strtolower', $prioridades)))],
            'visibility' => ['nullable', 'string', Rule::in(array_merge($visibilidades, $visibilidadesLower))],
            'due_at' => 'nullable|date',
            'remind_at' => 'nullable|date',
            'context' => 'nullable|array',
            'tags' => 'nullable|array',
            'watchers' => 'nullable|array',
            'watchers.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'visibility_users' => 'nullable|array',
            'visibility_users.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'visibility_departments' => 'nullable|array',
            'visibility_departments.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'O tipo do item é obrigatório.',
            'tipo.in' => 'Tipo inválido.',
            'title.required' => 'O título é obrigatório.',
            'title.max' => 'O título não pode ter mais de 255 caracteres.',
            'due_at.date' => 'A data/hora informada é inválida.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
