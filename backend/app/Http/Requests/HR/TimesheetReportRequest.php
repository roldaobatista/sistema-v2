<?php

namespace App\Http\Requests\HR;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class TimesheetReportRequest extends FormRequest
{
    use ValidatesTenantUser;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hr.schedule.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'month' => $this->filled('month') ? $this->input('month') : now()->format('Y-m'),
            'user_id' => $this->input('user_id') === '' ? null : $this->input('user_id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer', $this->tenantUserExistsRule()],
        ];
    }
}
