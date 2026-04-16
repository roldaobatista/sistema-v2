<?php

namespace App\Http\Requests\Crm;

use App\Models\ImportantDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportantDateRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'type' => ['required', Rule::in(array_keys(ImportantDate::TYPES))],
            'date' => 'required|date',
            'recurring_yearly' => 'boolean',
            'remind_days_before' => 'integer|min:1|max:60',
            'contact_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
