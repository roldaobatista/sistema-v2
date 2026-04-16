<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payable.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && $this->input('is_active') === '') {
            $this->merge(['is_active' => true]);
        }
        if ($this->has('sort_order') && $this->input('sort_order') === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('payment_methods')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('payment_methods')->where('tenant_id', $tenantId),
            ],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }

    private function tenantId(): int
    {
        return (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
