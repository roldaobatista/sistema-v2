<?php

namespace App\Http\Requests\Os;

class ArriveDisplacementRequest extends WorkOrderExecutionRequest
{
    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('latitude') && $this->input('latitude') === '') {
            $cleaned['latitude'] = null;
        }
        if ($this->has('longitude') && $this->input('longitude') === '') {
            $cleaned['longitude'] = null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
