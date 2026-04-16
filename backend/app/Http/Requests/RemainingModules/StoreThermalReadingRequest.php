<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class StoreThermalReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        return [
            'work_order_id' => 'required|integer',
            'equipment_id' => 'nullable|integer',
            'temperature' => 'required|numeric',
            'unit' => 'required|in:celsius,fahrenheit',
            'image_path' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
