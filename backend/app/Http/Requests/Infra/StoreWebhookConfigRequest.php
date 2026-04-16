<?php

namespace App\Http\Requests\Infra;

use App\Support\UrlSecurity;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('secret') && $this->input('secret') === '') {
            $this->merge(['secret' => null]);
        }
        if ($this->has('headers') && $this->input('headers') === '') {
            $this->merge(['headers' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:os.created,os.completed,os.cancelled,payment.received,quote.approved,lead.created,stock.low,calibration.due',
            'secret' => 'nullable|string|max:255',
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
