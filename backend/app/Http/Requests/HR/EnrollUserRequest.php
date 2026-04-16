<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.schedule.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('scheduled_date') && $this->input('scheduled_date') === '') {
            $this->merge(['scheduled_date' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'course_id' => ['required', 'integer', Rule::exists('training_courses', 'id')->where('tenant_id', $tenantId)],
            'scheduled_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O colaborador é obrigatório.',
            'course_id.required' => 'O curso é obrigatório.',
        ];
    }
}
