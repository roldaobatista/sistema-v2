<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceCallTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('observations') && $this->input('observations') === '') {
            $this->merge(['observations' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'observations' => 'nullable|string|max:2000',
            'equipment_ids' => 'nullable|array',
            'equipment_ids.*' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
