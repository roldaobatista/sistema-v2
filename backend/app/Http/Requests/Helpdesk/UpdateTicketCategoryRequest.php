<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('helpdesk.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sla_policy_id' => [
                'nullable',
                Rule::exists('sla_policies', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'default_priority' => 'nullable|string|in:low,medium,high,critical',
        ];
    }
}
