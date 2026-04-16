<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class CancelarNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.cancel');
    }

    public function rules(): array
    {
        return [
            'justificativa' => 'required|string|min:15|max:255',
        ];
    }
}
