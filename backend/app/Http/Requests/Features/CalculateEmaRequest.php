<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class CalculateEmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.view');
    }

    public function rules(): array
    {
        return [
            'precision_class' => 'required|string|in:I,II,III,IIII',
            'e_value' => 'required|numeric|gt:0',
            'loads' => 'required|array|min:1',
            'loads.*' => 'required|numeric|min:0',
            'verification_type' => 'nullable|string|in:initial,subsequent,in_use',
        ];
    }
}
