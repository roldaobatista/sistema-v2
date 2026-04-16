<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNonConformityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.nc.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'source' => 'sometimes|string|in:audit,customer_complaint,process_deviation',
            'severity' => 'sometimes|string|in:minor,major,critical',
            'status' => 'sometimes|string|in:open,investigating,corrective_action,closed',
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'due_date' => 'sometimes|nullable|date',
            'root_cause' => 'sometimes|nullable|string',
            'corrective_action' => 'sometimes|nullable|string',
            'preventive_action' => 'sometimes|nullable|string',
            'verification_notes' => 'sometimes|nullable|string',
            'capa_record_id' => ['sometimes', 'nullable', 'integer', Rule::exists('capa_records', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
