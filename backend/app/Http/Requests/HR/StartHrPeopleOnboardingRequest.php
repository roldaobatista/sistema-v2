<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartHrPeopleOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.onboarding.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'template_id' => 'required|integer',
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
