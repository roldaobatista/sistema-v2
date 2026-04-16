<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveBatchPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.approve');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payment_method') && $this->input('payment_method') === '') {
            $this->merge(['payment_method' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'ids' => 'required|array|min:1',
            'ids.*' => ['integer', Rule::exists('accounts_payable', 'id')->where('tenant_id', $tenantId)],
            'payment_method' => 'nullable|string|max:30',
        ];
    }
}
