<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagementReviewActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['responsible_id', 'due_date'] as $field) {
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
            'description' => 'required|string|max:2000',
            'responsible_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'due_date' => 'nullable|date',
        ];
    }
}
