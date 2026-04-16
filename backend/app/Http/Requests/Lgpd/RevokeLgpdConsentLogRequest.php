<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class RevokeLgpdConsentLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.consent.revoke');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
