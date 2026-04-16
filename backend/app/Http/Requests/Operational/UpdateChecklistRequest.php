<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.checklist.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'items' => 'sometimes|array',
            'items.*.id' => 'required_with:items|string',
            'items.*.text' => 'required_with:items|string',
            'items.*.type' => 'required_with:items|string|in:text,boolean,photo,select',
            'items.*.options' => 'nullable|array',
            'items.*.required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
