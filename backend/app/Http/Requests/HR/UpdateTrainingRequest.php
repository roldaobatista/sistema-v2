<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.training.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['institution', 'certificate_number', 'completion_date', 'expiry_date', 'category', 'hours', 'status', 'notes'];
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
        return [
            'title' => 'sometimes|string|max:255',
            'institution' => 'nullable|string|max:255',
            'certificate_number' => 'nullable|string|max:100',
            'completion_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'category' => 'nullable|in:technical,safety,quality,management',
            'hours' => 'nullable|integer|min:1',
            'status' => 'nullable|in:planned,in_progress,completed,expired',
            'notes' => 'nullable|string',
        ];
    }
}
