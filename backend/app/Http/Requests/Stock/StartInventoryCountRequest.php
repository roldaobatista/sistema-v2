<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['warehouse_id', 'product_ids', 'category'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = $field === 'product_ids' ? [] : null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'product_ids' => 'nullable|array',
            'category' => 'nullable|string|max:100',
        ];
    }
}
