<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQualityAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:internal,external,supplier',
            'scope' => 'nullable|string',
            'planned_date' => 'required|date',
            'auditor_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'items' => 'nullable|array',
            'items.*.requirement' => 'required_with:items|string',
            'items.*.clause' => 'nullable|string',
            'items.*.question' => 'required_with:items|string',
        ];
    }
}
