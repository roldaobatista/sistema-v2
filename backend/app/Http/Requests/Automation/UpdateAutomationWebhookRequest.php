<?php

namespace App\Http\Requests\Automation;

use App\Support\UrlSecurity;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('automation.webhook.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('secret') && $this->input('secret') === '') {
            $this->merge(['secret' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url',
            'events' => 'sometimes|array|min:1',
            'secret' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
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
