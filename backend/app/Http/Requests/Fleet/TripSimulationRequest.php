<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class TripSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.view');
    }

    public function rules(): array
    {
        return [
            'distance_km' => 'required|numeric|min:1',
            'avg_consumption' => 'required|numeric|min:0.1',
            'fuel_price' => 'required|numeric|min:0',
        ];
    }
}
