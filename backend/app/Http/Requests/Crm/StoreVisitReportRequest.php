<?php

namespace App\Http\Requests\Crm;

use App\Models\Commitment;
use App\Models\VisitReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitReportRequest extends FormRequest
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
            'checkin_id' => ['nullable', Rule::exists('visit_checkins', 'id')->where('tenant_id', $tenantId)],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'visit_date' => 'required|date',
            'visit_type' => ['nullable', Rule::in(array_keys(VisitReport::VISIT_TYPES))],
            'contact_name' => 'nullable|string|max:255',
            'contact_role' => 'nullable|string|max:255',
            'summary' => 'required|string',
            'decisions' => 'nullable|string',
            'next_steps' => 'nullable|string',
            'overall_sentiment' => ['nullable', Rule::in(array_keys(VisitReport::SENTIMENTS))],
            'topics' => 'nullable|array',
            'follow_up_scheduled' => 'boolean',
            'next_contact_at' => 'nullable|date',
            'next_contact_type' => 'nullable|string',
            'commitments' => 'nullable|array',
            'commitments.*.title' => 'required|string',
            'commitments.*.responsible_type' => ['required', Rule::in(array_keys(Commitment::RESPONSIBLE_TYPES))],
            'commitments.*.due_date' => 'nullable|date',
            'commitments.*.priority' => ['nullable', Rule::in(array_keys(Commitment::PRIORITIES))],
        ];
    }
}
