<?php

namespace App\Http\Requests\Os;

class PauseDisplacementRequest extends WorkOrderExecutionRequest
{
    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['latitude', 'longitude'] as $field) {
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
            'recorded_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'stop_type' => ['nullable', 'string', 'in:lunch,hotel,br_stop,fueling,technical_stop,other'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'reason.required' => 'O motivo da parada é obrigatório.',
        ];
    }
}
