<?php

namespace App\Http\Requests\Os;

class AddDisplacementStopRequest extends WorkOrderExecutionRequest
{
    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['latitude', 'longitude', 'notes'] as $field) {
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
            'type' => ['required', 'string', 'in:lunch,hotel,br_stop,other'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo da parada é obrigatório.',
            'type.in' => 'Tipo de parada inválido.',
        ];
    }
}
