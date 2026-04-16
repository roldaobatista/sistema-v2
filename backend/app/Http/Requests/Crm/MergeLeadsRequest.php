<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'primary_id' => [
                'required',
                'integer',
                Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'merge_ids' => 'required|array|min:1',
            'merge_ids.*' => [
                'integer',
                Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_id.required' => 'Informe o negócio principal.',
            'merge_ids.required' => 'Informe ao menos um negócio para mesclar.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
