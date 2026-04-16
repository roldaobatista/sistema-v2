<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.update');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        $existsCustomer = Rule::exists('customers', 'id')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        return [
            'primary_id' => ['required', 'integer', $existsCustomer],
            'duplicate_ids' => 'required|array|min:1',
            'duplicate_ids.*' => [
                'integer',
                $existsCustomer,
                'different:primary_id',
            ],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
