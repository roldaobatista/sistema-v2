<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.onboarding.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'template_id' => ['required', Rule::exists('onboarding_templates', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O colaborador é obrigatório.',
            'template_id.required' => 'O template é obrigatório.',
        ];
    }
}
