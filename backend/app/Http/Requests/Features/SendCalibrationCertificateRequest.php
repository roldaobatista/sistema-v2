<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class SendCalibrationCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('calibration.reading.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail do destinatário é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
        ];
    }
}
