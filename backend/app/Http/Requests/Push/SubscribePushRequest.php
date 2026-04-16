<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class SubscribePushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'endpoint' => 'required|url|max:2000',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ];
    }
}
