<?php

namespace App\Http\Requests\SystemImprovements;

use App\Models\CapaRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCapaRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'status' => [Rule::in(array_keys(CapaRecord::STATUSES))],
            'root_cause' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'preventive_action' => 'nullable|string',
            'verification' => 'nullable|string',
            'effectiveness' => [Rule::in(array_keys(CapaRecord::EFFECTIVENESS))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'due_date' => 'nullable|date',
        ];
    }
}
