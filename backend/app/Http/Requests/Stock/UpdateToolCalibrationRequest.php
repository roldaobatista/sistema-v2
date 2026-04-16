<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateToolCalibrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    public function rules(): array
    {
        return [
            'calibration_date' => 'sometimes|date',
            'next_calibration_date' => 'nullable|date',
            'certificate_number' => 'nullable|string|max:100',
            'performed_by' => 'nullable|string|max:255',
            'result' => 'nullable|in:approved,rejected,adjusted,conditional',
            'status' => 'nullable|in:pending,approved,rejected,adjusted,conditional',
            'notes' => 'nullable|string',
        ];
    }
}
