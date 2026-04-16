<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmitNfeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'quote_id' => ['nullable', 'integer', Rule::exists('quotes', 'id')->where('tenant_id', $tenantId)],
            'nature_of_operation' => 'nullable|string|max:60',
            'cfop' => 'nullable|string|max:4',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:120',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.ncm' => 'nullable|string|max:10',
            'items.*.cfop' => 'nullable|string|max:4',
            'items.*.unit' => 'nullable|string|max:6',
            'items.*.code' => 'nullable|string|max:60',
            'items.*.cest' => 'nullable|string|max:10',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.csosn' => 'nullable|string|max:3',
            'items.*.icms_cst' => 'nullable|string|max:3',
            'items.*.icms_origin' => 'nullable|string|max:1',
            'items.*.icms_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.icms_credit_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.pis_cst' => 'nullable|string|max:2',
            'items.*.pis_rate' => 'nullable|numeric|min:0',
            'items.*.cofins_cst' => 'nullable|string|max:2',
            'items.*.cofins_rate' => 'nullable|numeric|min:0',
            'items.*.ipi_cst' => 'nullable|string|max:2',
            'items.*.ipi_rate' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:2',
            'informacoes_complementares' => 'nullable|string|max:5000',
        ];
    }
}
