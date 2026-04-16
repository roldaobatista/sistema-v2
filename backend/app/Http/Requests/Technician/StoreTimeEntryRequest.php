<?php

namespace App\Http\Requests\Technician;

use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.time_entry.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description === '' ? null : $this->description,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'work_order_id' => [
                'required',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'technician_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'schedule_id' => [
                'nullable',
                Rule::exists('schedules', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'type' => ['sometimes', Rule::in(array_keys(TimeEntry::TYPES))],
            'description' => 'nullable|string',
        ];
    }
}
