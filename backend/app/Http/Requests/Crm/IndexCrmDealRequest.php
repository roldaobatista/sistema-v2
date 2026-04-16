<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmDeal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.view');
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['pipeline_id', 'assigned_to', 'customer_id', 'per_page'] as $field) {
            if ($this->has($field) && preg_match('/^-?\d+$/', (string) $this->input($field)) === 1) {
                $normalized[$field] = (int) $this->input($field);
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
            'pipeline_id' => ['nullable', 'integer', Rule::exists('crm_pipelines', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', 'string', Rule::in(array_keys(CrmDeal::STATUSES))],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
