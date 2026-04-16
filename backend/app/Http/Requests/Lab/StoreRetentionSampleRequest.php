<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetentionSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['work_order_id', 'notes'] as $field) {
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
        $tenantId = $this->user()->current_tenant_id;

        return [
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'sample_code' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'location' => 'required|string|max:100',
            'retention_days' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
