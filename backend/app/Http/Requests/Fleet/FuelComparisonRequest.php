<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class FuelComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.view');
    }

    public function rules(): array
    {
        return [
            'gasoline_price' => 'required|numeric|min:0',
            'ethanol_price' => 'required|numeric|min:0',
            'diesel_price' => 'nullable|numeric|min:0',
        ];
    }
}
