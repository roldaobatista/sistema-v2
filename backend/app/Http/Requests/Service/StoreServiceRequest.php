<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.service.create');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'category_id' => [
                'nullable',
                Rule::exists('service_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('services', 'code')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'default_price' => 'nullable|numeric|min:0',
            'estimated_minutes' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
