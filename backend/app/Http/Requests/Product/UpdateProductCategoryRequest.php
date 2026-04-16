<?php

namespace App\Http\Requests\Product;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.product.update');
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof ProductCategory ? $category->id : (int) $category;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')
                    ->where('tenant_id', $this->tenantId())
                    ->ignore($categoryId),
            ],
            'is_active' => 'nullable|boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
