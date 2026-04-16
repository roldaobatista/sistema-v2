<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    public function rules(): array
    {
        return [
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ];
    }
}
