<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductKitItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.product.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? app('current_tenant_id') ?? 0);

        return [
            'child_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'quantity' => 'required|numeric|min:0.0001',
        ];
    }
}
