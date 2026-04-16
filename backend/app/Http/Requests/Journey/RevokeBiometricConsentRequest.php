<?php

namespace App\Http\Requests\Journey;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class RevokeBiometricConsentRequest extends FormRequest
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
        ];
    }
}
