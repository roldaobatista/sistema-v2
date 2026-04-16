<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StagesReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.pipeline.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'stage_ids' => 'required|array',
            'stage_ids.*' => Rule::exists('crm_pipeline_stages', 'id')->where('tenant_id', $tenantId),
        ];
    }
}
