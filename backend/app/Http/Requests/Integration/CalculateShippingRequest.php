<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class CalculateShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.view');
    }

    public function rules(): array
    {
        return [
            'origin_zip' => 'required|string|max:9',
            'destination_zip' => 'required|string|max:9',
            'weight_kg' => 'required|numeric|min:0.1',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric',
            'dimensions.width' => 'nullable|numeric',
            'dimensions.height' => 'nullable|numeric',
        ];
    }
}
