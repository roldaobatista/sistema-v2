<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['provider', 'employee_contribution', 'end_date', 'notes'];
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['sometimes', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'type' => 'string',
            'provider' => 'nullable|string',
            'value' => 'numeric|min:0',
            'employee_contribution' => 'nullable|numeric|min:0',
            'start_date' => 'date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
