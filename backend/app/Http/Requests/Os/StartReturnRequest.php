<?php

namespace App\Http\Requests\Os;

class StartReturnRequest extends WorkOrderExecutionRequest
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
            'destination' => ['required', 'string', 'in:base,hotel,next_client,other'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'destination.required' => 'O destino do retorno é obrigatório.',
            'destination.in' => 'Destino inválido.',
        ];
    }
}
