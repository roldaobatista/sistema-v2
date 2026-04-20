<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('iam.audit_log.view');
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'auditable_type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
