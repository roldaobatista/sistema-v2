<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class EmitirComplementarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && $this->input('items') === '') {
            $this->merge(['items' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'valor_complementar' => 'required|numeric|min:0.01',
            'items' => 'nullable|array',
        ];
    }
}
