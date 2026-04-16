<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array',
            'items.*.id' => 'required|string',
            'items.*.text' => 'required|string',
            'items.*.type' => 'required|string|in:text,boolean,photo,select',
            'items.*.options' => 'nullable|array',
            'items.*.required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
