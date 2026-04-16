<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.template.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:100',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signatory_registration' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ];
    }
}
