<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.manage.rules');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['descricao', 'evento_trigger', 'tipo_item', 'status_trigger', 'prioridade_minima', 'acao_config', 'responsavel_user_id', 'role_alvo'];
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
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'ativo' => ['sometimes', 'boolean'],
            'evento_trigger' => ['nullable', 'string', 'max:120'],
            'tipo_item' => ['nullable', 'string', Rule::in($tipoItemIn)],
            'status_trigger' => ['nullable', 'string', 'max:50'],
            'prioridade_minima' => ['nullable', 'string', Rule::in($prioridadeIn)],
            'acao_tipo' => ['required', 'string', Rule::in(['auto_assign', 'set_priority', 'set_due', 'notify'])],
            'acao_config' => ['nullable', 'array'],
            'responsavel_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'role_alvo' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
