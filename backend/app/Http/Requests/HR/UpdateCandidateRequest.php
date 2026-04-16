<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.recruitment.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['phone', 'resume_path', 'notes', 'rejected_reason'];
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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:20',
            'resume_path' => 'nullable|string',
            'stage' => 'sometimes|in:applied,screening,interview,technical_test,offer,hired,rejected',
            'notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'rejected_reason' => 'nullable|string',
        ];
    }
}
