<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.work_schedule.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'break_start', 'break_end', 'tolerance_minutes', 'overtime_allowed'];
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'technician_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'date' => 'required|date',
            'shift_type' => 'nullable|in:normal,overtime,off,vacation,sick',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'region' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }
}
