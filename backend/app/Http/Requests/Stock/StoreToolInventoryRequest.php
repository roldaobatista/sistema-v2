<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['serial_number', 'description', 'assigned_to', 'status', 'purchase_date', 'purchase_value'] as $field) {
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'status' => 'nullable|in:available,in_use,maintenance,retired',
            'purchase_date' => 'nullable|date',
            'purchase_value' => 'nullable|numeric|min:0',
        ];
    }
}
