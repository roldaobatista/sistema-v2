<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['expected_close_date', 'source', 'assigned_to', 'quote_id', 'work_order_id', 'equipment_id', 'notes'];
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
            'title' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0',
            'probability' => 'sometimes|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'source' => 'nullable|string|max:50',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'quote_id' => ['nullable', Rule::exists('quotes', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'notes' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
