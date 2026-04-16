<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['notes', 'deadline', 'supplier_ids'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = $field === 'supplier_ids' ? [] : null;
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
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'deadline' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.specifications' => 'nullable|string|max:500',
            'supplier_ids' => 'nullable|array',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
