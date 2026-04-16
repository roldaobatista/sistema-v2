<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['status', 'resolution', 'resolution_notes'] as $field) {
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
            'status' => 'nullable|in:requested,approved,in_transit,received,inspected,resolved,rejected',
            'resolution' => 'nullable|in:refund,replacement,repair,credit,rejected',
            'resolution_notes' => 'nullable|string|max:1000',
        ];
    }
}
