<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSsoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_domain') && $this->input('tenant_domain') === '') {
            $this->merge(['tenant_domain' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:google,microsoft,okta',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'tenant_domain' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
