<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class IndexNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}
