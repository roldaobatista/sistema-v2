<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.organization.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['parent_id', 'manager_id', 'cost_center'];
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
            'name' => 'sometimes|string|max:255',
            'parent_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'manager_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'cost_center' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
