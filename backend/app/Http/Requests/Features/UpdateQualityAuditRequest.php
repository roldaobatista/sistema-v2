<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQualityAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:internal,external,process,product,supplier',
            'status' => 'nullable|in:planned,in_progress,completed,cancelled',
            'planned_date' => 'sometimes|date',
            'scheduled_date' => 'nullable|date',
            'executed_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'auditor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'scope' => 'nullable|string|max:2000',
            'summary' => 'nullable|string|max:5000',
            'non_conformities_found' => 'nullable|integer|min:0',
            'observations_found' => 'nullable|integer|min:0',
        ];
    }
}
