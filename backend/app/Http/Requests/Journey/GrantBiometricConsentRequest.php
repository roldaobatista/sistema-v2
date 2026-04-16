<?php

namespace App\Http\Requests\Journey;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class GrantBiometricConsentRequest extends FormRequest
{
    use ValidatesTenantUser;

    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', $this->tenantUserExistsRule()],
            'data_type' => ['required', 'string', 'in:geolocation,facial,fingerprint,voice'],
            'legal_basis' => ['required', 'string', 'in:consent,legitimate_interest,legal_obligation'],
            'purpose' => ['required', 'string', 'max:500'],
            'alternative_method' => ['nullable', 'string', 'max:100'],
            'retention_days' => ['nullable', 'integer', 'min:30', 'max:1825'],
        ];
    }
}
