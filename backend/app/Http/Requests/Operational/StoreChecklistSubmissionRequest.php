<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.checklist.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'checklist_id' => ['required', Rule::exists('checklists', 'id')->where('tenant_id', $tenantId)],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'responses' => 'required|array',
            'completed_at' => 'nullable|date',
        ];
    }
}
