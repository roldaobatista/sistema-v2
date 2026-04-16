<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNonConformityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.nc.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'source' => 'required|string|in:audit,customer_complaint,process_deviation',
            'severity' => 'required|string|in:minor,major,critical',
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'due_date' => 'nullable|date|after_or_equal:today',
            'quality_audit_id' => ['nullable', 'integer', Rule::exists('quality_audits', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
