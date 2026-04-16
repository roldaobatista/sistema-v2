<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class RrStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.manage');
    }

    public function rules(): array
    {
        return [
            'measurements' => 'required|array|min:3',
            'measurements.*.operator' => 'required|string',
            'measurements.*.trial' => 'required|integer',
            'measurements.*.part' => 'required|integer',
            'measurements.*.value' => 'required|numeric',
        ];
    }
}
