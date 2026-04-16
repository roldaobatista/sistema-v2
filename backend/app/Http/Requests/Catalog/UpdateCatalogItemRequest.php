<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
