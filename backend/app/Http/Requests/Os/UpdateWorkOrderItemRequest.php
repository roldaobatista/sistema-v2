<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_id' => $this->reference_id === '' ? null : $this->reference_id,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:product,service',
            'reference_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('tenant_id', $this->user()->current_tenant_id)],
            'description' => 'sometimes|string',
            'quantity' => 'sometimes|numeric|min:0.01',
            'unit_price' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
        ];
    }
}
