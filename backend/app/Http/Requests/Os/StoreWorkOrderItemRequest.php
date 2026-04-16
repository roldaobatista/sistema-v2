<?php

namespace App\Http\Requests\Os;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', 'service'),
            'reference_id' => $this->reference_id === '' ? null : $this->reference_id,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $quantity = (float) $this->input('quantity', 1);
            $unitPrice = (float) $this->input('unit_price', 0);
            $discount = (float) $this->input('discount', 0);
            $itemTotal = $quantity * $unitPrice;

            if ($discount > $itemTotal && $itemTotal > 0) {
                $validator->errors()->add('discount', 'O desconto não pode ser maior que o valor total do item (R$ '.number_format($itemTotal, 2, ',', '.').').');
            }
        });
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        $referenceRule = ['nullable', 'integer'];
        if ($this->input('type') === 'product' && $this->input('reference_id')) {
            $referenceRule[] = Rule::exists('products', 'id')->where('tenant_id', $tenantId);
        } elseif ($this->input('type') === 'service' && $this->input('reference_id')) {
            $referenceRule[] = Rule::exists('services', 'id')->where('tenant_id', $tenantId);
        }

        return [
            'type' => 'required|in:product,service',
            'reference_id' => $referenceRule,
            'description' => 'required|string',
            'quantity' => 'sometimes|numeric|min:0.01|max:99999999.99',
            'unit_price' => 'sometimes|numeric|min:0|max:99999999.99',
            'discount' => 'sometimes|numeric|min:0|max:99999999.99',
            'is_courtesy' => 'sometimes|boolean',
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }
}
