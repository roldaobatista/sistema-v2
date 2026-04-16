<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmitNfseRequest extends FormRequest
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
            'services' => 'required|array|min:1',
            'services.*.description' => 'required|string|max:2000',
            'services.*.amount' => 'required|numeric|min:0',
            'services.*.quantity' => 'nullable|numeric|min:1',
            'services.*.service_code' => 'nullable|string|max:20',
            'services.*.lc116_code' => 'nullable|string|max:10',
            'services.*.municipal_service_code' => 'nullable|string|max:20',
            'services.*.cnae_code' => 'nullable|string|max:20',
            'services.*.iss_rate' => 'nullable|numeric|min:0|max:100',
            'services.*.iss_retained' => 'nullable|boolean',
            'services.*.deductions' => 'nullable|numeric|min:0',
            'services.*.discount' => 'nullable|numeric|min:0',
            'iss_rate' => 'nullable|numeric|min:0|max:100',
            'iss_retained' => 'nullable|boolean',
            'exigibilidade_iss' => 'nullable|string|in:1,2,3,4,5,6,7',
            'natureza_tributacao' => 'nullable|string|max:2',
            'pis_rate' => 'nullable|numeric|min:0',
            'cofins_rate' => 'nullable|numeric|min:0',
            'inss_rate' => 'nullable|numeric|min:0',
            'ir_rate' => 'nullable|numeric|min:0',
            'csll_rate' => 'nullable|numeric|min:0',
            'informacoes_complementares' => 'nullable|string|max:5000',
        ];
    }
}
