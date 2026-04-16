<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.manage');
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant_id');

        return [
            'certificate_id' => [
                'required',
                'integer',
                Rule::exists('equipment_calibrations', 'id')->where('tenant_id', $tenantId),
            ],
            'signer_name' => 'required|string|max:255',
            'signer_role' => 'required|in:technical_responsible,quality_manager,laboratory_director',
        ];
    }
}
