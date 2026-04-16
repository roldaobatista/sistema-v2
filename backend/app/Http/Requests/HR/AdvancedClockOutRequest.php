<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class AdvancedClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['latitude', 'longitude', 'notes'] as $field) {
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
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
