<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class EmitirRemessaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['natureza', 'cfop'] as $field) {
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
            'customer_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'natureza' => 'nullable|string|max:255',
            'cfop' => 'nullable|string|max:10',
        ];
    }
}
