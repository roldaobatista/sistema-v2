<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class DensityViabilityInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'base_lat' => 'required|numeric',
            'base_lng' => 'required|numeric',
        ];
    }
}
