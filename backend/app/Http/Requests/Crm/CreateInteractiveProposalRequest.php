<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInteractiveProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.proposal.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'quote_id' => ['required', Rule::exists('quotes', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'expires_at' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'quote_id.required' => 'O orçamento é obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
