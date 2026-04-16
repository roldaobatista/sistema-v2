<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEscalationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('helpdesk.create');
    }

    public function rules(): array
    {
        return [
            'sla_policy_id' => [
                'required',
                Rule::exists('sla_policies', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'name' => 'required|string|max:255',
            'trigger_minutes' => 'required|integer|min:1',
            'action_type' => 'required|string|in:notify,reassign,change_priority',
            'action_payload' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }
}
