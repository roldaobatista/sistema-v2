<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchUpdateBudgetLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.expense.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'limits' => 'required|array|min:1',
            'limits.*.id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'limits.*.budget_limit' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'limits.required' => 'Informe ao menos um limite.',
            'limits.*.id.exists' => 'Categoria de despesa não encontrada.',
            'limits.*.budget_limit.min' => 'O limite orçamentário não pode ser negativo.',
        ];
    }
}
