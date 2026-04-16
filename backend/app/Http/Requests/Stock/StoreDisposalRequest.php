<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['environmental_notes', 'warehouse_id'] as $field) {
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
            'disposal_type' => 'required|in:expired,damaged,obsolete,recalled,hazardous,other',
            'disposal_method' => 'required|in:recycling,incineration,landfill,donation,return_manufacturer,specialized_treatment',
            'justification' => 'required|string|max:2000',
            'environmental_notes' => 'nullable|string|max:1000',
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.batch_id' => ['nullable', Rule::exists('batches', 'id')->where('tenant_id', $tenantId)],
            'items.*.notes' => 'nullable|string|max:500',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
