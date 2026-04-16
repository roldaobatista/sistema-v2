<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQualityAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['requirement', 'clause', 'result', 'status', 'notes', 'evidence'];
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
            'description' => 'sometimes|string|max:1000',
            'requirement' => 'nullable|string|max:500',
            'clause' => 'nullable|string|max:100',
            'question' => 'sometimes|string|max:1000',
            'result' => 'nullable|in:conform,non_conform,observation,not_applicable,conforming,non_conforming',
            'status' => 'nullable|in:conform,non_conform,observation,not_applicable,conforming,non_conforming',
            'notes' => 'nullable|string|max:2000',
            'evidence' => 'nullable|string|max:1000',
        ];
    }
}
