<?php

namespace App\Http\Requests\Journey;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreTechnicianCertificationRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'document_path' => ['nullable', 'string', 'max:500'],
            'required_for_service_types' => ['nullable', 'array'],
        ];
    }
}
