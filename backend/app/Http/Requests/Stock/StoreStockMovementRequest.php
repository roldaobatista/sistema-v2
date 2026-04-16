<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['batch_id', 'product_serial_id', 'unit_cost', 'notes'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'batch_id' => ['nullable', Rule::exists('batches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'product_serial_id' => ['nullable', Rule::exists('product_serials', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'type' => 'required|in:entry,exit,reserve,return,adjustment',
            'quantity' => ['required', 'numeric', $this->input('type') === 'adjustment' ? 'not_in:0' : 'min:0.01'],
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'O produto e obrigatório.',
            'warehouse_id.required' => 'O armazem e obrigatório.',
            'type.required' => 'O tipo de movimentacao e obrigatório.',
            'quantity.required' => 'A quantidade e obrigatoria.',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
