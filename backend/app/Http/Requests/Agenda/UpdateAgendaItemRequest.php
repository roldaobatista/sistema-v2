<?php

namespace App\Http\Requests\Agenda;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.close.self');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['short_description', 'due_at', 'remind_at', 'snooze_until', 'tags', 'visibility_users', 'visibility_departments'];
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
        $tenantId = $this->tenantId();
        $statuses = array_map(fn ($c) => $c->value, AgendaItemStatus::cases());
        $prioridades = array_map(fn ($c) => $c->value, AgendaItemPriority::cases());
        $visibilidades = array_map(fn ($c) => $c->value, AgendaItemVisibility::cases());

        return [
            'title' => 'sometimes|string|max:255',
            'short_description' => 'nullable|string|max:255',
            'status' => ['sometimes', 'string', Rule::in(array_merge($statuses, array_map('strtolower', $statuses)))],
            'priority' => ['sometimes', 'string', Rule::in(array_merge($prioridades, array_map('strtolower', $prioridades)))],
            'visibility' => ['sometimes', 'string', Rule::in(array_merge($visibilidades, array_map('strtolower', $visibilidades)))],
            'assignee_user_id' => ['sometimes', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'due_at' => 'nullable|date',
            'remind_at' => 'nullable|date',
            'snooze_until' => 'nullable|date',
            'tags' => 'nullable|array',
            'visibility_users' => 'nullable|array',
            'visibility_users.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'visibility_departments' => 'nullable|array',
            'visibility_departments.*' => 'integer',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
