<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRenegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.renegotiation.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'receivable_ids' => 'required|array|min:1',
            'receivable_ids.*' => [Rule::exists('accounts_receivable', 'id')->where('tenant_id', $tenantId)],
            'negotiated_total' => 'required|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
            'interest_amount' => 'nullable|numeric|min:0',
            'fine_amount' => 'nullable|numeric|min:0',
            'new_installments' => 'required|integer|min:1|max:60',
            'first_due_date' => 'required|date|after:today',
            'notes' => 'nullable|string',
        ];
    }
}
