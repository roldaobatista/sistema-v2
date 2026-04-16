<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payable.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->current_tenant_id;

        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['required', 'integer', Rule::exists('account_payable_categories', 'id')->where('tenant_id', $tenantId)],
            'chart_of_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
