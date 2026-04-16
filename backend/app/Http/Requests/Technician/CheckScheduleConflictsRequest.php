<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckScheduleConflictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.schedule.view');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'technician_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'exclude_schedule_id' => [
                'nullable',
                'integer',
                Rule::exists('schedules', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
