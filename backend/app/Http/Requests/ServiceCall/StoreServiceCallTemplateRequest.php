<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceCallTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('observations') && $this->input('observations') === '') {
            $this->merge(['observations' => null]);
        }
        if ($this->has('is_active') && $this->input('is_active') === '') {
            $this->merge(['is_active' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'priority' => 'required|string|in:low,normal,high,urgent',
            'observations' => 'nullable|string|max:2000',
            'equipment_ids' => 'nullable|array',
            'equipment_ids.*' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
