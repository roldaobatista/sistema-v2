<?php

namespace App\Http\Requests\ServiceOps;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutoAssignRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['conditions', 'technician_ids', 'required_skills', 'priority'] as $field) {
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
            'strategy' => 'sometimes|string|in:round_robin,least_loaded,skill_match,proximity',
            'conditions' => 'nullable|array',
            'technician_ids' => 'nullable|array',
            'required_skills' => 'nullable|array',
            'priority' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
        ];
    }
}
