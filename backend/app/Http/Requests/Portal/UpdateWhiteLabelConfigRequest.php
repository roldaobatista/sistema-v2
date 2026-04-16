<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhiteLabelConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('portal.config.manage');
    }

    public function rules(): array
    {
        return [
            'company_name' => 'nullable|string|max:255',
            'logo_url' => 'nullable|url',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'custom_css' => 'nullable|string|max:5000',
            'custom_domain' => 'nullable|string|max:255',
        ];
    }
}
