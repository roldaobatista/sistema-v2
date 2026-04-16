<?php

namespace App\Http\Requests\Journey;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class IndexTechnicianCertificationRequest extends FormRequest
{
    use ValidatesTenantUser;

    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', $this->tenantUserExistsRule()],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:valid,expiring_soon,expired,revoked'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
