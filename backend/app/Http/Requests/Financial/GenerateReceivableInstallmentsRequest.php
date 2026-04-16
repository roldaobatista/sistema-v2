<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReceivableInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'work_order_id' => [
                'required_without:customer_id',
                'nullable',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'customer_id' => [
                'required_without:work_order_id',
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'total_amount' => 'required_without:work_order_id|nullable|numeric|min:0.01',
            'description' => 'required_without:work_order_id|nullable|string|max:255',
            'installments' => 'required|integer|min:2|max:48',
            'first_due_date' => 'required|date|after_or_equal:today',
            'payment_method' => 'nullable|string|max:30',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
