<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContinuousFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.feedback.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'to_user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'content' => 'required|string',
            'type' => 'required|in:praise,suggestion,concern',
            'visibility' => 'required|in:public,private,manager_only',
            'attachment' => 'nullable|file|image|max:5120',
        ];
    }
}
