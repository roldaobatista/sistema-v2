<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'latitude' => $this->latitude === '' ? null : $this->latitude,
            'longitude' => $this->longitude === '' ? null : $this->longitude,
            'notes' => $this->notes === '' ? null : $this->notes,
        ]);
    }

    public function rules(): array
    {
        return [
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ];
    }
}
