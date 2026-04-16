<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInmetroBaseConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.import');
    }

    public function rules(): array
    {
        return [
            'base_lat' => 'nullable|numeric|between:-90,90',
            'base_lng' => 'nullable|numeric|between:-180,180',
            'base_address' => 'nullable|string|max:500',
            'base_city' => 'nullable|string|max:100',
            'base_state' => 'nullable|string|size:2',
            'max_distance_km' => 'integer|min:10|max:2000',
        ];
    }
}
