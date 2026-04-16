<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class CreateCalibrationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.create');
    }

    public function rules(): array
    {
        return [
            'calibration_type' => 'nullable|string|in:initial,periodic,after_repair,special',
            'received_date' => 'nullable|date',
            'calibration_location' => 'nullable|string|max:255',
            'calibration_location_type' => 'nullable|string|in:laboratory,field,client_site',
            'verification_type' => 'nullable|string|in:initial,subsequent',
            'precision_class' => 'nullable|string|in:I,II,III,IIII',
            'verification_division_e' => 'nullable|numeric|min:0',
            'calibration_method' => 'nullable|string|in:comparison,substitution,direct',
            'temperature' => 'nullable|numeric|min:-50|max:100',
            'humidity' => 'nullable|numeric|min:0|max:100',
            'pressure' => 'nullable|numeric|min:0',
        ];
    }
}
