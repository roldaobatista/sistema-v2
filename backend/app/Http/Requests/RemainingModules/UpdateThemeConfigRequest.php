<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'dark_mode' => 'nullable|boolean',
            'sidebar_style' => 'nullable|in:default,compact,minimal',
            'font_family' => 'nullable|string|max:50',
            'logo_url' => 'nullable|string',
        ];
    }
}
