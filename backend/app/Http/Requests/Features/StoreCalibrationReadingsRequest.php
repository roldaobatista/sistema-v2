<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalibrationReadingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.create');
    }

    public function rules(): array
    {
        return [
            'readings' => 'required|array|min:1',
            'readings.*.reference_value' => 'required|numeric',
            'readings.*.indication_increasing' => 'nullable|numeric',
            'readings.*.indication_decreasing' => 'nullable|numeric',
            'readings.*.k_factor' => 'nullable|numeric',
            'readings.*.repetition' => 'nullable|integer',
            'readings.*.unit' => 'nullable|string|max:10',
        ];
    }
}
