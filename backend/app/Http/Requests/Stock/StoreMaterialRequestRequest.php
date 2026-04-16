<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['warehouse_id', 'work_order_id', 'priority', 'justification'] as $field) {
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
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'priority' => 'nullable|in:low,normal,high,urgent',
            'justification' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity_requested' => 'required|numeric|min:0.01',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
