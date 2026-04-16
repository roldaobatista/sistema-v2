<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringCommissionRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('commissions.recurring.create');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'user_id' => ['required', $this->tenantUserExistsRule()],
            'recurring_contract_id' => ['required', Rule::exists('recurring_contracts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'commission_rule_id' => ['required', Rule::exists('commission_rules', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }
}
