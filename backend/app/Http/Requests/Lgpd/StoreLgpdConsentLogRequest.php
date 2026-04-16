<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class StoreLgpdConsentLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.consent.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'holder_type' => ['required', 'string', 'in:App\\Models\\User,App\\Models\\Customer,App\\Models\\CustomerContact'],
            'holder_id' => ['required', 'integer'],
            'holder_name' => ['required', 'string', 'max:255'],
            'holder_email' => ['nullable', 'email', 'max:255'],
            'holder_document' => ['nullable', 'string', 'max:20'],
            'purpose' => ['required', 'string', 'max:255'],
            'legal_basis' => ['required', 'string', 'max:255'],
        ];
    }
}
