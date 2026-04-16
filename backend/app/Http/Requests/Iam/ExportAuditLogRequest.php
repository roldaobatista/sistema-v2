<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('iam.audit_log.export') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->current_tenant_id;

        return [
            'action' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'auditable_type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
