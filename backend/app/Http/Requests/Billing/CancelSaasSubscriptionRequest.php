<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class CancelSaasSubscriptionRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
