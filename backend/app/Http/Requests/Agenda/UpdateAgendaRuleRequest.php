<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.manage.rules');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'event_trigger', 'item_type', 'status_trigger', 'min_priority', 'action_config', 'assignee_user_id', 'target_role'];
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
        $tipoItemIn = [
            'service_call', 'work_order', 'financial', 'quote', 'task', 'reminder', 'calibration', 'contract', 'other',
        ];
        $prioridadeIn = ['low', 'medium', 'high', 'urgent'];

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
            'event_trigger' => ['nullable', 'string', 'max:120'],
            'item_type' => ['nullable', 'string', Rule::in($tipoItemIn)],
            'status_trigger' => ['nullable', 'string', 'max:50'],
            'min_priority' => ['nullable', 'string', Rule::in($prioridadeIn)],
            'action_type' => ['sometimes', 'string', Rule::in(['auto_assign', 'set_priority', 'set_due', 'notify'])],
            'action_config' => ['nullable', 'array'],
            'assignee_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'target_role' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
