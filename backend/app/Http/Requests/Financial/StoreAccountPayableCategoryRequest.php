<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountPayableCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payable.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['color', 'description'];
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
            'name' => ['required', 'string', 'max:100', Rule::unique('account_payable_categories')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
