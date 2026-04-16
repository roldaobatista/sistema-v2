<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class InutilizarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'serie' => 'required|integer',
            'numero_inicial' => 'required|integer|min:1',
            'numero_final' => 'required|integer|min:1|gte:numero_inicial',
            'justificativa' => 'required|string|min:15|max:255',
        ];
    }
}
