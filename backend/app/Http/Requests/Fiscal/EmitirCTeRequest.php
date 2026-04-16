<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class EmitirCTeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer',
            'valor_total' => 'required|numeric|min:0.01',
        ];
    }
}
