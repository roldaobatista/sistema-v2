<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.pipeline.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('color') && $this->input('color') === '') {
            $this->merge(['color' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'color' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
