<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class ExportAccountantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.view');
    }

    public function rules(): array
    {
        return [
            'mes' => 'required|date_format:Y-m',
        ];
    }
}
