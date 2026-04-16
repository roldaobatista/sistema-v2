<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class CompleteTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.schedule.manage');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['score', 'certification_number'] as $field) {
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
            'score' => 'nullable|numeric|min:0|max:100',
            'certification_number' => 'nullable|string|max:255',
        ];
    }
}
