<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('procurement.purchase_quotation.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'reference' => 'nullable|string|max:255',
            'supplier_id' => [
                'sometimes',
                'required',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => 'nullable|string|max:255',
            'total' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'valid_until' => 'nullable|date',
        ];
    }
}
