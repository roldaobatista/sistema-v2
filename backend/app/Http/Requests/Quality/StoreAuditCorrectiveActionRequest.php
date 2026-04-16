<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditCorrectiveActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.update');
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant_id');

        return [
            'description' => 'required|string',
            'root_cause' => 'nullable|string',
            'quality_audit_item_id' => [
                'nullable',
                'integer',
                Rule::exists('quality_audit_items', 'id')->where(function ($query) use ($tenantId) {
                    $query->whereIn('quality_audit_id', function ($sub) use ($tenantId) {
                        $sub->select('id')->from('quality_audits')->where('tenant_id', $tenantId);
                    });
                }),
            ],
            'responsible_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'due_date' => 'nullable|date',
        ];
    }
}
