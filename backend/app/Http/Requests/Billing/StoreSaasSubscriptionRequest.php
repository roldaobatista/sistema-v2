<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaasSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.subscription.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'exists:saas_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,annual'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'trial_ends_at' => ['nullable', 'date'],
            'payment_gateway' => ['nullable', 'string', 'in:asaas,stripe,manual'],
        ];
    }
}
