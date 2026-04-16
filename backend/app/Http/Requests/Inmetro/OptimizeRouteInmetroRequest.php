<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class OptimizeRouteInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.convert');
    }

    public function rules(): array
    {
        return [
            'base_lat' => 'required|numeric',
            'base_lng' => 'required|numeric',
            'owner_ids' => 'required|array|min:1',
        ];
    }
}
