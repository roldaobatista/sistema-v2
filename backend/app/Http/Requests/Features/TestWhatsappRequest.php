<?php

namespace App\Http\Requests\Features;

use App\Support\BrazilPhone;
use Illuminate\Foundation\Http\FormRequest;

class TestWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.whatsapp.manage');
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (! is_string($phone) || trim($phone) === '') {
            return;
        }

        $normalizedPhone = BrazilPhone::whatsappDigits($phone);

        if ($normalizedPhone !== null) {
            $this->merge([
                'phone' => $normalizedPhone,
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $phone = $this->input('phone');

            if (is_string($phone) && trim($phone) !== '' && BrazilPhone::whatsappDigits($phone) === null) {
                $validator->errors()->add('phone', 'Informe um telefone brasileiro válido com DDD.');
            }
        });
    }
}
