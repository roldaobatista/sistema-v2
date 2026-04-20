<?php

namespace App\Http\Requests\HR;

use App\Support\CurrentTenantResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.work_schedule.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'tolerance_minutes', 'overtime_allowed'];
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
        $tenantId = CurrentTenantResolver::resolveForUser($this->user());

        return [
            'technician_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'date' => 'sometimes|date',
            'shift_type' => 'sometimes|nullable|in:normal,overtime,off,vacation,sick',
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i|after:start_time',
            'region' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }
}
