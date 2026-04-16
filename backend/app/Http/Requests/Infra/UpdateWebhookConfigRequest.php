<?php

namespace App\Http\Requests\Infra;

use App\Support\UrlSecurity;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('headers') && $this->input('headers') === '') {
            $this->merge(['headers' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:500',
            'events' => 'sometimes|array|min:1',
            'is_active' => 'boolean',
            'headers' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $url = $this->input('url');
            if ($url && ! UrlSecurity::isSafeUrl($url)) {
                $validator->errors()->add('url', 'A URL informada aponta para uma rede interna e não é permitida.');
            }
        });
    }
}
