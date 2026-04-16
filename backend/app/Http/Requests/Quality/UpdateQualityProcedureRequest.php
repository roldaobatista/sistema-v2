<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQualityProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.procedure.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'category', 'content', 'next_review_date', 'status'];
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
            'description' => 'nullable|string',
            'category' => 'nullable|in:calibration,safety,operational,management',
            'content' => 'nullable|string',
            'next_review_date' => 'nullable|date',
            'status' => 'nullable|in:draft,active,obsolete',
        ];
    }
}
