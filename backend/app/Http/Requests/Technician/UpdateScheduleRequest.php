<?php

namespace App\Http\Requests\Technician;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.schedule.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'work_order_id' => [
                'nullable',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'technician_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'title' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
            'scheduled_start' => 'sometimes|date',
            'scheduled_end' => 'sometimes|date|after_or_equal:scheduled_start',
            'status' => ['sometimes', 'string', Rule::in(array_keys(Schedule::STATUSES))],
            'address' => 'nullable|string|max:500',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
