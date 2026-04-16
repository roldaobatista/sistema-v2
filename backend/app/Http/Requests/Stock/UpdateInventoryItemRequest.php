<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.inventory.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'counted_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'counted_quantity.required' => 'A quantidade contada é obrigatória.',
            'counted_quantity.min' => 'A quantidade contada não pode ser negativa.',
        ];
    }
}
