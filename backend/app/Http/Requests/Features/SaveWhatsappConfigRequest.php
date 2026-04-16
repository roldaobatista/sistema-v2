<?php

namespace App\Http\Requests\Features;

use App\Support\BrazilPhone;
use App\Support\UrlSecurity;
use Illuminate\Foundation\Http\FormRequest;

class SaveWhatsappConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.whatsapp.manage');
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:evolution,z-api,meta',
            'api_url' => 'required|url',
            'api_key' => 'required|string',
            'instance_name' => 'nullable|string',
            'phone_number' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phoneNumber = $this->input('phone_number');

        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            return;
        }

        $normalizedPhone = BrazilPhone::whatsappDigits($phoneNumber);

        if ($normalizedPhone !== null) {
            $this->merge([
                'phone_number' => $normalizedPhone,
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $url = $this->input('api_url');
            if ($url && ! UrlSecurity::isSafeUrl($url)) {
                $validator->errors()->add('api_url', 'A URL informada aponta para uma rede interna e não é permitida.');
            }

            $phoneNumber = $this->input('phone_number');
            if (is_string($phoneNumber) && trim($phoneNumber) !== '' && BrazilPhone::whatsappDigits($phoneNumber) === null) {
                $validator->errors()->add('phone_number', 'Informe um telefone brasileiro válido com DDD.');
            }
        });
    }
}
