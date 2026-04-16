<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.milestone.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'billing_value' => 'nullable|numeric|min:0',
            'weight' => 'sometimes|numeric|min:0.1',
            'order' => 'required|integer|min:1',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'integer',
            'deliverables' => 'nullable|string',
        ];
    }
}
