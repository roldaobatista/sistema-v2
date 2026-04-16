<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class CreateChecklistInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'instrument_type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'items' => 'required|array',
            'regulation_reference' => 'nullable|string|max:100',
        ];
    }
}
