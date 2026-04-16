<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.warehouse.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['manufacturing_date', 'expires_at', 'supplier_id'];
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
        $batch = $this->route('batch');
        $tenantId = $this->tenantId();

        return [
            'batch_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('batches', 'code')->where('tenant_id', $tenantId)->ignore($batch->id),
            ],
            'manufacturing_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:manufacturing_date',
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
