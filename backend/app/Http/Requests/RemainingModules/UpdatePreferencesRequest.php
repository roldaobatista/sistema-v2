<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'dark_mode' => 'nullable|boolean',
            'language' => 'nullable|in:pt_BR,en_US,es_ES',
            'notifications' => 'nullable|boolean',
            'data_saver' => 'nullable|boolean',
            'offline_sync' => 'nullable|boolean',
        ];
    }
}
