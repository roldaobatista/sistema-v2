<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreHrPeopleOnboardingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.onboarding.view');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.description' => 'nullable|string|max:1000',
            'steps.*.days_offset' => 'required|integer|min:0',
            'steps.*.assignee_role' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do template é obrigatório.',
            'role.required' => 'O cargo é obrigatório.',
            'steps.required' => 'Pelo menos uma etapa é obrigatória.',
        ];
    }
}
