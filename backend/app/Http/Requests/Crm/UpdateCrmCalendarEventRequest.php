<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmCalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => [Rule::in(array_keys(CrmCalendarEvent::TYPES))],
            'start_at' => 'date',
            'end_at' => 'date',
            'all_day' => 'boolean',
            'location' => 'nullable|string',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where('tenant_id', $tenantId)],
            'color' => 'nullable|string',
        ];
    }
}
