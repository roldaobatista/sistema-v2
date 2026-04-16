<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.assign');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['user_id', 'responsavel_user_id'];
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

        return [
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'responsavel_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'user_id ou responsavel_user_id é obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
