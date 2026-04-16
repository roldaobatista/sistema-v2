<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.transfer.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('notes') && $this->input('notes') === '') {
            $cleaned['notes'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'from_warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'to_warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
                'different:from_warehouse_id',
            ],
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'from_warehouse_id.required' => 'O armazém de origem é obrigatório.',
            'from_warehouse_id.exists' => 'Armazém de origem inválido.',
            'to_warehouse_id.required' => 'O armazém de destino é obrigatório.',
            'to_warehouse_id.exists' => 'Armazém de destino inválido.',
            'to_warehouse_id.different' => 'Origem e destino devem ser diferentes.',
            'items.required' => 'Informe ao menos um item.',
            'items.*.product_id.required' => 'Produto do item é obrigatório.',
            'items.*.quantity.required' => 'Quantidade do item é obrigatória.',
            'items.*.quantity.min' => 'Quantidade deve ser maior que zero.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
