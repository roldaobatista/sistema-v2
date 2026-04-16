<?php

namespace App\Http\Requests\Calibration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccreditationScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accreditation.scope.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'accreditation_number' => ['sometimes', 'string', 'max:100'],
            'accrediting_body' => ['sometimes', 'string', 'max:100'],
            'scope_description' => ['sometimes', 'string'],
            'equipment_categories' => ['sometimes', 'array', 'min:1'],
            'equipment_categories.*' => ['required', 'string'],
            'valid_from' => ['sometimes', 'date'],
            'valid_until' => ['sometimes', 'date', 'after:valid_from'],
            'certificate_file' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
