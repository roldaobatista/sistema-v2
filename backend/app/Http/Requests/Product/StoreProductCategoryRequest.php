<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.product.create');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')->where('tenant_id', $this->tenantId()),
            ],
            'is_active' => 'nullable|boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
