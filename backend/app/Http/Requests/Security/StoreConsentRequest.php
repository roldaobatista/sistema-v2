<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.security.create');
    }

    public function rules(): array
    {
        return [
            'consent_type' => 'required|in:data_processing,marketing,analytics,cookies,third_party',
            'granted' => 'required|boolean',
        ];
    }
}
