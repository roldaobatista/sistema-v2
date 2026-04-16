<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class ValidateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.view');
    }

    public function rules(): array
    {
        return [
            'documento' => 'required|string|max:20',
        ];
    }
}
