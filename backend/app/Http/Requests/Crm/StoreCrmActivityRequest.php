<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['deal_id', 'contact_id', 'description', 'scheduled_at', 'completed_at', 'duration_minutes', 'outcome', 'channel', 'metadata'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'type' => ['required', Rule::in(array_keys(CrmActivity::TYPES))],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'contact_id' => ['nullable', Rule::exists('customer_contacts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:0',
            'outcome' => ['nullable', Rule::in(array_keys(CrmActivity::OUTCOMES))],
            'channel' => ['nullable', Rule::in(array_keys(CrmActivity::CHANNELS))],
            'metadata' => 'nullable|array',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
