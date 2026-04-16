<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountPayableCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payable.update');
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
        $category = $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('account_payable_categories')->ignore($category->id)->where(fn ($q) => $q->where('tenant_id', $category->tenant_id))],
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
