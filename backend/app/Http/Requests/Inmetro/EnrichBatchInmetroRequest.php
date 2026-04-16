<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrichBatchInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.enrich');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'owner_ids' => 'required|array|max:50',
            'owner_ids.*' => ['integer', Rule::exists('inmetro_owners', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
