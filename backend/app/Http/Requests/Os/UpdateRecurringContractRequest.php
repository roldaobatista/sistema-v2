<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['equipment_id', 'assigned_to', 'description', 'end_date', 'priority'];
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
            'customer_id' => ['sometimes', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'sometimes|in:weekly,biweekly,monthly,bimonthly,quarterly,semiannual,annual',
            'end_date' => 'nullable|date',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'is_active' => 'sometimes|boolean',
            'items' => 'nullable|array',
            'items.*.type' => 'required_with:items|in:product,service',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
