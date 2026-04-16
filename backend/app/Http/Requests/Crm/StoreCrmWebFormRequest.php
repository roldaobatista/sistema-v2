<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmWebFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.form.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string',
            'fields.*.type' => 'required|string',
            'fields.*.label' => 'required|string',
            'fields.*.required' => 'boolean',
            'pipeline_id' => ['nullable', Rule::exists('crm_pipelines', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'assign_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'sequence_id' => ['nullable', Rule::exists('crm_sequences', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'redirect_url' => 'nullable|url',
            'success_message' => 'nullable|string',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
