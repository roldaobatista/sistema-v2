<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.cost_center.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:20',
            'parent_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('tenant_id', $tenantId)],
            'is_active' => 'nullable|boolean',
        ];
    }
}
