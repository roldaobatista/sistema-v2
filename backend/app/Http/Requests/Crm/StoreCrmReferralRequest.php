<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.referral.manage');
    }

    public function rules(): array
    {
        $tid = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'referrer_customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tid))],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tid))],
            'referred_name' => 'required|string|max:255',
            'referred_email' => 'nullable|email',
            'referred_phone' => 'nullable|string',
            'reward_type' => ['nullable', Rule::in(array_keys(CrmReferral::REWARD_TYPES))],
            'reward_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
