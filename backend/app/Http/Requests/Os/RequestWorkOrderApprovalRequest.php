<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestWorkOrderApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('notes') && $this->input('notes') === '') {
            $cleaned['notes'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'approver_ids' => 'required|array|min:1',
            'approver_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'approver_ids.required' => 'Informe ao menos um aprovador.',
            'approver_ids.*.exists' => 'Um ou mais aprovadores são inválidos.',
        ];
    }
}
