<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkServiceCallActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['technician_id', 'priority'];
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
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;

        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|string|in:assign_technician,change_priority',
            'technician_id' => ['required_if:action,assign_technician', 'nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)->where(fn ($sub) => $sub->where('tenant_id', $tenantId)->orWhere('current_tenant_id', $tenantId)))],
            'priority' => 'required_if:action,change_priority|nullable|string|in:low,normal,high,urgent',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'É necessário informar ao menos um chamado.',
            'action.required' => 'A ação é obrigatória.',
            'action.in' => 'Ação inválida.',
        ];
    }
}
