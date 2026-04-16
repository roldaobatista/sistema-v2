<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class RespondToNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject,snooze,acknowledge',
        ];
    }
}
