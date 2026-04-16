<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.warehouse.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['manufacturing_date', 'expires_at', 'supplier_id', 'initial_quantity'];
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
            'batch_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('batches', 'code')->where('tenant_id', $tenantId),
            ],
            'manufacturing_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:manufacturing_date',
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'initial_quantity' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'O produto e obrigatório.',
            'batch_number.required' => 'O numero do lote e obrigatório.',
            'batch_number.unique' => 'já existe um lote com este numero.',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
