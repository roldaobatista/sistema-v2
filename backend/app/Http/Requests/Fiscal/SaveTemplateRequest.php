<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.view');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'type' => 'required|in:nfe,nfse',
            'template_data' => 'required|array',
        ];
    }
}
