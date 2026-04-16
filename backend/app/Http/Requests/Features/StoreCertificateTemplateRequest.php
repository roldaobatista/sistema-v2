<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.template.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signatory_registration' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ];
    }
}
