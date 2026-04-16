<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.payable.update');
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
        $method = $this->route('paymentMethod');

        return [
            'name' => 'sometimes|string|max:100',
            'code' => [
                'sometimes', 'string', 'max:30',
                Rule::unique('payment_methods')
                    ->where('tenant_id', $method->tenant_id)
                    ->ignore($method->id),
            ],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }
}
