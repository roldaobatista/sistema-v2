<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use App\Models\CommissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionRuleRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('commissions.rule.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['user_id', 'source_filter'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($this->has('applies_to_role')) {
            $cleaned['applies_to_role'] = CommissionRule::normalizeRole($this->input('applies_to_role'));
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', $this->tenantUserExistsRule()],
            'name' => 'sometimes|string|max:255',
            'type' => ['sometimes', Rule::in([CommissionRule::TYPE_PERCENTAGE, CommissionRule::TYPE_FIXED])],
            'value' => 'sometimes|numeric|min:0',
            'applies_to' => ['sometimes', Rule::in([CommissionRule::APPLIES_ALL, CommissionRule::APPLIES_PRODUCTS, CommissionRule::APPLIES_SERVICES])],
            'calculation_type' => ['sometimes', Rule::in(array_keys(CommissionRule::CALCULATION_TYPES))],
            'applies_to_role' => ['sometimes', Rule::in(CommissionRule::acceptedRoleValues())],
            'applies_when' => ['sometimes', Rule::in([CommissionRule::WHEN_OS_COMPLETED, CommissionRule::WHEN_INSTALLMENT_PAID, CommissionRule::WHEN_OS_INVOICED])],
            'tiers' => 'nullable|array',
            'priority' => 'sometimes|integer',
            'active' => 'sometimes|boolean',
            'source_filter' => 'nullable|string|max:100',
        ];
    }
}
