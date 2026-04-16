<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeightAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.standard_weight.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['returned_at', 'notes'] as $field) {
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
            'status' => 'sometimes|in:assigned,returned,lost',
            'returned_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
