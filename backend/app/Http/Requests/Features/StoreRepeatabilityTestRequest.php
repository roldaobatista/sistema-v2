<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepeatabilityTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.create');
    }

    public function rules(): array
    {
        return [
            'load_value' => 'required|numeric|gt:0',
            'unit' => 'nullable|string|max:10',
            'measurements' => 'required|array|min:2|max:10',
            'measurements.*' => 'required|numeric',
        ];
    }
}
