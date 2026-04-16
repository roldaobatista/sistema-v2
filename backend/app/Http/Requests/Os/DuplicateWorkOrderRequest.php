<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'include_items' => ['sometimes', 'boolean'],
            'include_equipments' => ['sometimes', 'boolean'],
            'new_customer_id' => ['sometimes', 'nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
