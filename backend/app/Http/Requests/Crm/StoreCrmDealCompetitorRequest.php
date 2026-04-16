<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmDealCompetitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmDealCompetitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
        $dealExists = Rule::exists('crm_deals', 'id')->where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });

        return [
            'deal_id' => ['required', $dealExists],
            'competitor_name' => 'required|string|max:255',
            'competitor_price' => 'nullable|numeric|min:0',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'outcome' => ['nullable', Rule::in(array_keys(CrmDealCompetitor::OUTCOMES))],
        ];
    }
}
