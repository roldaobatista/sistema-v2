<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.equipment_model.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['brand', 'category'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'name' => 'sometimes|required|string|max:150',
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:40',
        ];
    }
}
