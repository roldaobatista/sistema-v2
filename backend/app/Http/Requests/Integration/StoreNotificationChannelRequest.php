<?php

namespace App\Http\Requests\Integration;

use App\Support\UrlSecurity;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('channel_name') && $this->input('channel_name') === '') {
            $this->merge(['channel_name' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:slack,teams',
            'webhook_url' => 'required|url',
            'channel_name' => 'nullable|string|max:100',
            'events' => 'required|array|min:1',
            'events.*' => 'in:os.created,os.completed,quote.approved,payment.received,alert.critical',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $url = $this->input('webhook_url');
            if ($url && ! UrlSecurity::isSafeUrl($url)) {
                $validator->errors()->add('webhook_url', 'A URL informada aponta para uma rede interna e não é permitida.');
            }
        });
    }
}
