<?php

namespace App\Http\Requests\ServiceOps;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutoAssignRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
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
            'name' => 'required|string|max:255',
            'entity_type' => 'required|string|in:work_order,service_call',
            'strategy' => 'required|string|in:round_robin,least_loaded,skill_match,proximity',
            'conditions' => 'nullable|array',
            'technician_ids' => 'nullable|array',
            'required_skills' => 'nullable|array',
            'priority' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
        ];
    }
}
