<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.schedule.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
        if ($this->has('certification_validity_months') && $this->input('certification_validity_months') === '') {
            $this->merge(['certification_validity_months' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'duration_hours' => 'required|integer|min:1',
            'certification_validity_months' => 'nullable|integer|min:1|max:120',
            'is_mandatory' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do curso é obrigatório.',
            'duration_hours.required' => 'A duração em horas é obrigatória.',
        ];
    }
}
