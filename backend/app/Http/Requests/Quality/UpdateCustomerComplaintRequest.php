<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.complaint.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['status', 'resolution', 'assigned_to', 'response_due_at', 'responded_at'];
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
            'status' => 'nullable|in:open,investigating,resolved,closed',
            'resolution' => 'nullable|string',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'response_due_at' => 'nullable|date',
            'responded_at' => 'nullable|date',
        ];
    }
}
