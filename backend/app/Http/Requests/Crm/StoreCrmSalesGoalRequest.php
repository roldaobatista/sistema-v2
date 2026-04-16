<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmSalesGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmSalesGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.goal.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'territory_id' => ['nullable', Rule::exists('crm_territories', 'id')->where('tenant_id', $tenantId)],
            'period_type' => ['required', Rule::in(array_keys(CrmSalesGoal::PERIOD_TYPES))],
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'target_revenue' => 'required|numeric|min:0',
            'target_deals' => 'required|integer|min:0',
            'target_new_customers' => 'integer|min:0',
            'target_activities' => 'integer|min:0',
        ];
    }
}
