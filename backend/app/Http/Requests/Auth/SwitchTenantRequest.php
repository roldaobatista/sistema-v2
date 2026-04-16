<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SwitchTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.tenant.switch');
    }

    public function rules(): array
    {
        // tenant_id as direct input is intentional here — user explicitly
        // chooses target tenant. Access control is handled in controller
        // via hasTenantAccess() which returns 403 without revealing tenant
        // existence (security best practice: no tenant enumeration).
        return [
            'tenant_id' => 'required|integer',
        ];
    }
}
