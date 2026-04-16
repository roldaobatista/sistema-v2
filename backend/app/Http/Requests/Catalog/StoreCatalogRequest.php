<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('service_catalogs', 'slug')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'subtitle' => 'nullable|string|max:255',
            'header_description' => 'nullable|string',
            'is_published' => 'boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
