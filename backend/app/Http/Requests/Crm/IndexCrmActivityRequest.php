<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.view');
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['customer_id', 'contact_id', 'deal_id', 'per_page'] as $field) {
            if ($this->has($field) && preg_match('/^-?\d+$/', (string) $this->input($field)) === 1) {
                $normalized[$field] = (int) $this->input($field);
            }
        }

        if ($this->has('pending') && ! is_bool($this->input('pending'))) {
            $pending = filter_var($this->input('pending'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($pending !== null) {
                $normalized['pending'] = $pending;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'contact_id' => ['nullable', 'integer', Rule::exists('customer_contacts', 'id')->where('tenant_id', $tenantId)],
            'deal_id' => ['nullable', 'integer', Rule::exists('crm_deals', 'id')->where('tenant_id', $tenantId)],
            'type' => ['nullable', 'string', Rule::in(array_keys(CrmActivity::TYPES))],
            'pending' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
