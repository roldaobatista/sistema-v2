<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class StoreExcentricityTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.create');
    }

    public function rules(): array
    {
        return [
            'tests' => 'required|array|min:1',
            'tests.*.position' => 'required|string|max:50',
            'tests.*.load_applied' => 'required|numeric',
            'tests.*.indication' => 'required|numeric',
            'tests.*.max_permissible_error' => 'nullable|numeric',
        ];
    }
}
