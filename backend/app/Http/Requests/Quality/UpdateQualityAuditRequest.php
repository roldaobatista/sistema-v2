<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQualityAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['scheduled_date', 'executed_date', 'completed_date', 'auditor_id', 'scope', 'summary', 'non_conformities_found', 'observations_found'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:internal,external,process,product,supplier',
            'status' => 'sometimes|in:planned,in_progress,completed,cancelled',
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
