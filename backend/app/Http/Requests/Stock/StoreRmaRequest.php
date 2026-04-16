<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['customer_id', 'supplier_id', 'work_order_id'] as $field) {
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
        $tenantId = $this->tenantId();

        return [
            'type' => 'required|in:customer_return,supplier_return',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => 'nullable|integer',
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'reason' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.defect_description' => 'nullable|string|max:500',
            'items.*.condition' => 'nullable|in:new,used,damaged,defective',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
