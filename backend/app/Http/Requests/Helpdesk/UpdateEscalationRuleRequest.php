<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEscalationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('helpdesk.update');
    }

    public function rules(): array
    {
        return [
            'sla_policy_id' => [
                'sometimes',
                'required',
                Rule::exists('sla_policies', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'name' => 'sometimes|required|string|max:255',
            'trigger_minutes' => 'sometimes|required|integer|min:1',
            'action_type' => 'sometimes|required|string|in:notify,reassign,change_priority',
            'action_payload' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }
}
