<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.referral.manage');
    }

    public function rules(): array
    {
        $tid = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
        $cust = Rule::exists('customers', 'id')->where(function ($q) use ($tid) {
            $q->where('tenant_id', $tid);
        });
        $deal = Rule::exists('crm_deals', 'id')->where(function ($q) use ($tid) {
            $q->where('tenant_id', $tid);
        });

        return [
            'status' => ['nullable', Rule::in(array_keys(CrmReferral::STATUSES))],
            'referred_customer_id' => ['nullable', $cust],
            'deal_id' => ['nullable', $deal],
            'reward_type' => ['nullable', Rule::in(array_keys(CrmReferral::REWARD_TYPES))],
            'reward_value' => 'nullable|numeric|min:0',
            'reward_given' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
