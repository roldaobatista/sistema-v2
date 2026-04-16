<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class CheckRegimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.view');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|max:50',
        ];
    }
}
