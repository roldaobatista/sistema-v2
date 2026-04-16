<?php

namespace App\Http\Requests\Crm;

use App\Models\Commitment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'visit_report_id' => ['nullable', Rule::exists('visit_reports', 'id')->where('tenant_id', $tenantId)],
            'activity_id' => ['nullable', Rule::exists('crm_activities', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'responsible_type' => ['required', Rule::in(array_keys(Commitment::RESPONSIBLE_TYPES))],
            'responsible_name' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'priority' => ['nullable', Rule::in(array_keys(Commitment::PRIORITIES))],
        ];
    }
}
