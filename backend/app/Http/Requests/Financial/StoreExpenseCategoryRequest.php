<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.expense.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'budget_limit' => 'nullable|numeric|min:0',
            'default_affects_net_value' => 'boolean',
            'default_affects_technician_cash' => 'boolean',
        ];
    }
}
