<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductSerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['warehouse_id', 'status'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'serial_number' => 'required|string|max:255',
            'status' => 'nullable|in:available,sold,maintenance,discarded',
        ];
    }
}
