<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class AssetTagScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['action', 'location', 'latitude', 'longitude'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'action' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
