<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class ManifestarDestinatarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'chave_acesso' => 'required|string|size:44',
            'tipo' => 'required|in:ciencia,confirmacao,desconhecimento,nao_realizada',
        ];
    }
}
