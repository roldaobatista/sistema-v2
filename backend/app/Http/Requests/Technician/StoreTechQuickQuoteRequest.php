<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechQuickQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.technician.create');
    }

    public function rules(): array
    {
        $user = $this->user();
        $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id);

        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'equipment_id' => [
                'required',
                Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'observations' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:service,product',
            'items.*.product_id' => [
                'nullable',
                'required_if:items.*.type,product',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.service_id' => [
                'nullable',
                'required_if:items.*.type,service',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }
}
