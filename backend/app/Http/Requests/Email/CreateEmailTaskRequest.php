<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmailTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.create_task');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['title', 'responsible_id'];
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
            'type' => 'required|in:task,service_call,work_order',
            'title' => 'nullable|string|max:255',
            'responsible_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo do item é obrigatório.',
            'type.in' => 'Tipo inválido. Use: task, service_call ou work_order.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
