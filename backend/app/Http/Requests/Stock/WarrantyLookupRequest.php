<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.view');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['serial_number', 'work_order_id', 'equipment_id'] as $field) {
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
            'serial_number' => 'nullable|string|max:255',
            'work_order_id' => 'nullable|integer',
            'equipment_id' => 'nullable|integer',
        ];
    }
}
