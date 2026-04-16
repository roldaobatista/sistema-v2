<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReceivableFromWorkOrderRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'due_date' => 'required|date',
            'payment_method' => 'nullable|string|max:30',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
