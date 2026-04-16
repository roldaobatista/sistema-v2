<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UseAgendaTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['responsavel_user_id', 'titulo', 'descricao_curta', 'due_at'];
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
            'responsavel_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'titulo' => 'nullable|string|max:255',
            'descricao_curta' => 'nullable|string|max:500',
            'due_at' => 'nullable|date',
        ];
    }
}
