<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class NearbyLeadsInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ];
    }
}
