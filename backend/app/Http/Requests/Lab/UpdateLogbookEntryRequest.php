<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['temperature', 'humidity'] as $field) {
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
            'entry_date' => 'sometimes|date',
            'type' => 'sometimes|in:observation,incident,maintenance,calibration,visitor,other',
            'description' => 'sometimes|string',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric',
        ];
    }
}
