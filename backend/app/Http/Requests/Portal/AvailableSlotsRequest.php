<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class AvailableSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date|after:today',
            'service_type' => 'nullable|string',
        ];
    }
}
