<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class UploadCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    public function rules(): array
    {
        return [
            'certificate' => 'required|file|max:10240|mimes:pfx,p12',
            'password' => 'required|string|max:255',
        ];
    }
}
