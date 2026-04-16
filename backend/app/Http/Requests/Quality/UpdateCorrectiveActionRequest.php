<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCorrectiveActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.corrective_action.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['root_cause', 'action_plan', 'responsible_id', 'deadline', 'status', 'verification_notes'];
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
            'root_cause' => 'nullable|string',
            'action_plan' => 'nullable|string',
            'responsible_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'deadline' => 'nullable|date',
            'status' => 'nullable|in:open,in_progress,completed,verified',
            'verification_notes' => 'nullable|string',
        ];
    }
}
