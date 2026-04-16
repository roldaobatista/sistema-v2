<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordWinLossInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'outcome' => 'required|in:win,loss',
            'owner_id' => ['nullable', Rule::exists('inmetro_owners', 'id')->where('tenant_id', $tenantId)],
            'competitor_id' => ['nullable', Rule::exists('inmetro_competitors', 'id')->where('tenant_id', $tenantId)],
            'reason' => 'nullable|string|max:100',
            'estimated_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'outcome_date' => 'nullable|date',
        ];
    }
}
