<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class StoreSensorReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('location') && $this->input('location') === '') {
            $this->merge(['location' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'sensor_id' => 'required|string|max:50',
            'sensor_type' => 'required|in:temperature,humidity,pressure,vibration',
            'value' => 'required|numeric',
            'unit' => 'required|string|max:10',
            'location' => 'nullable|string|max:100',
        ];
    }
}
