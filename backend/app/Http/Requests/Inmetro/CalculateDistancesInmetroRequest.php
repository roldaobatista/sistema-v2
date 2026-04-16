<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class CalculateDistancesInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.import');
    }

    public function rules(): array
    {
        return [
            'base_lat' => 'required|numeric|between:-90,90',
            'base_lng' => 'required|numeric|between:-180,180',
        ];
    }
}
