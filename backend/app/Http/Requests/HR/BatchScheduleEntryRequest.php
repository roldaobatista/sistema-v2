<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchScheduleEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.schedule.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'schedules' => 'required|array|min:1',
            'schedules.*.user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'schedules.*.date' => 'required|date',
            'schedules.*.shift_type' => 'nullable|in:normal,overtime,off,vacation,sick',
            'schedules.*.start_time' => 'nullable|date_format:H:i',
            'schedules.*.end_time' => 'nullable|date_format:H:i',
            'schedules.*.region' => 'nullable|string|max:100',
        ];
    }
}
