<?php

namespace App\Http\Requests\Crm;

use App\Models\QuickNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuickNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'channel' => ['nullable', Rule::in(array_keys(QuickNote::CHANNELS))],
            'sentiment' => ['nullable', Rule::in(array_keys(QuickNote::SENTIMENTS))],
            'content' => 'required|string',
            'is_pinned' => 'boolean',
            'tags' => 'nullable|array',
        ];
    }
}
