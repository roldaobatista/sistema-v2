<?php

namespace App\Http\Requests\Calibration;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccreditationScopeRequest extends FormRequest
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
            'accreditation_number' => ['required', 'string', 'max:100'],
            'accrediting_body' => ['sometimes', 'string', 'max:100'],
            'scope_description' => ['required', 'string'],
            'equipment_categories' => ['required', 'array', 'min:1'],
            'equipment_categories.*' => ['required', 'string'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'certificate_file' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
