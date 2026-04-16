<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTechnicianCertificationRequest extends FormRequest
{
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
            'name' => ['sometimes', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'document_path' => ['nullable', 'string', 'max:500'],
            'required_for_service_types' => ['nullable', 'array'],
        ];
    }
}
