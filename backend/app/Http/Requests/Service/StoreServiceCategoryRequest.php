<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.service.create');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_categories', 'name')->where('tenant_id', $this->tenantId()),
            ],
            'is_active' => 'nullable|boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
