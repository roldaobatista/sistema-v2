<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class UpdateManagementReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['participants', 'agenda', 'decisions', 'summary'];
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
            'meeting_date' => 'sometimes|date',
            'title' => 'sometimes|string|max:255',
            'participants' => 'nullable|string|max:2000',
            'agenda' => 'nullable|string|max:5000',
            'decisions' => 'nullable|string|max:5000',
            'summary' => 'nullable|string|max:5000',
        ];
    }
}
