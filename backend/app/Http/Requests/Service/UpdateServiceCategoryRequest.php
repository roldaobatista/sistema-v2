<?php

namespace App\Http\Requests\Service;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.service.update');
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof ServiceCategory ? $category->id : (int) $category;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('service_categories', 'name')
                    ->where('tenant_id', $this->tenantId())
                    ->ignore($categoryId),
            ],
            'is_active' => 'nullable|boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
