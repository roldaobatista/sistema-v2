<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReconciliationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create') || $this->user()->can('finance.payable.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['match_value', 'match_amount_min', 'match_amount_max', 'target_type', 'target_id', 'category', 'customer_id', 'supplier_id'];
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
            'name' => 'required|string|max:255',
            'match_field' => 'required|in:description,amount,cnpj,combined',
            'match_operator' => 'required|in:contains,equals,regex,between',
            'match_value' => 'nullable|string|max:500',
            'match_amount_min' => 'nullable|numeric|min:0',
            'match_amount_max' => 'nullable|numeric|min:0',
            'action' => 'required|in:match_receivable,match_payable,ignore,categorize',
            'target_type' => 'nullable|string',
            'target_id' => 'nullable|integer',
            'category' => 'nullable|string|max:100',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'priority' => 'integer|min:1|max:100',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da regra é obrigatório.',
            'match_field.required' => 'O campo de comparação é obrigatório.',
            'match_operator.required' => 'O operador é obrigatório.',
            'action.required' => 'A ação é obrigatória.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
