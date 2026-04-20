<?php

namespace App\Http\Requests\HR;

use App\Support\CurrentTenantResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.schedule.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shift_type' => $this->shift_type === '' ? null : $this->shift_type,
            'start_time' => $this->start_time === '' ? null : $this->start_time,
            'end_time' => $this->end_time === '' ? null : $this->end_time,
            'region' => $this->region === '' ? null : $this->region,
            'notes' => $this->notes === '' ? null : $this->notes,
        ]);
    }

    public function rules(): array
    {
        $tenantId = CurrentTenantResolver::resolveForUser($this->user());

        return [
            'technician_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'date' => 'required|date',
            'shift_type' => 'nullable|in:normal,overtime,off,vacation,sick',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'region' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }
}
