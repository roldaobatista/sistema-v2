<?php

namespace App\Http\Requests\HR;

use App\Models\Lookups\OnboardingTemplateType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('type')) {
            return;
        }

        $type = strtolower(trim((string) $this->input('type')));
        $aliases = [
            'onboarding' => 'admission',
            'offboarding' => 'dismissal',
        ];

        if (isset($aliases[$type])) {
            $this->merge(['type' => $aliases[$type]]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('hr.onboarding.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
        $allowedTypes = LookupValueResolver::allowedValues(
            OnboardingTemplateType::class,
            [
                'admission' => 'Admissao',
                'dismissal' => 'Desligamento',
                'onboarding' => 'Onboarding',
                'offboarding' => 'Offboarding',
            ],
            $tenantId
        );

        return [
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in($allowedTypes)],
            'default_tasks' => 'nullable|array',
            'default_tasks.*.title' => 'required|string|max:255',
            'default_tasks.*.description' => 'nullable|string|max:500',
            'tasks' => 'nullable|array',
            'tasks.*' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do template é obrigatório.',
            'type.required' => 'O tipo é obrigatório.',
        ];
    }
}
