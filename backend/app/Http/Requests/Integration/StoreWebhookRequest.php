<?php

namespace App\Http\Requests\Integration;

use App\Support\UrlSecurity;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.manage') ?? false;
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
            'url' => 'required|url',
            'event' => 'required|in:os.created,os.completed,os.cancelled,work_order.created,work_order.completed,work_order.cancelled,quote.approved,payment.received,certificate.issued,customer.created',
            'secret' => 'nullable|string',
            'is_active' => 'boolean',
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
