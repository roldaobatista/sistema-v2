<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.product.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->input('code', $this->input('sku')),
            'stock_qty' => $this->input('stock_qty', $this->input('current_stock', 0)),
            'stock_min' => $this->input('stock_min', $this->input('minimum_stock', 0)),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'category_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'code')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'sometimes|string|max:10',
            'cost_price' => 'nullable|numeric|min:0',
            'sell_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'nullable|numeric|min:0',
            'stock_min' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'track_stock' => 'nullable|boolean',
            'is_kit' => 'nullable|boolean',
            'track_batch' => 'nullable|boolean',
            'track_serial' => 'nullable|boolean',
            'min_repo_point' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'default_supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId),
            ],
            'manufacturer_code' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:100',
            'ncm' => 'nullable|string|max:10',
            'image_url' => 'nullable|url|max:500',
            'barcode' => 'nullable|string|max:50',
            'brand' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'depth' => 'nullable|numeric|min:0',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
