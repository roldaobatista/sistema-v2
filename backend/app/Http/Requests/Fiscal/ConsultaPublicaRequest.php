<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class ConsultaPublicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.view');
    }

    public function rules(): array
    {
        return [
            'chave_acesso' => 'required|string|size:44',
        ];
    }
}
