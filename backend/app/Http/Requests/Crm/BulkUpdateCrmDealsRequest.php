<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCrmDealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'deal_ids' => 'required|array|min:1|max:100',
            'deal_ids.*' => ['integer', Rule::exists('crm_deals', 'id')->where('tenant_id', $tenantId)],
            'action' => 'required|in:move_stage,mark_won,mark_lost,delete',
            'stage_id' => ['required_if:action,move_stage', 'integer', Rule::exists('crm_pipeline_stages', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
