<?php

namespace App\Http\Requests\Crm;

use App\Models\AccountPlanAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountPlanActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => [Rule::in(array_keys(AccountPlanAction::STATUSES))],
            'due_date' => 'nullable|date',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
