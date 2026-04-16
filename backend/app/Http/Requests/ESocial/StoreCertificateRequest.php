<?php

namespace App\Http\Requests\ESocial;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.esocial.create');
    }

    public function rules(): array
    {
        return [
            'certificate' => ['required', 'file', 'max:10240'],
            'password' => ['required', 'string'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
        ];
    }
}
