<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class StoreLgpdDataTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.treatment.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'data_category' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'legal_basis' => ['required', 'string', 'in:consent,legal_obligation,contract_execution,legitimate_interest,vital_interest,public_policy,research,credit_protection'],
            'description' => ['nullable', 'string', 'max:2000'],
            'data_types' => ['required', 'string', 'max:500'],
            'retention_period' => ['nullable', 'string', 'max:255'],
            'retention_legal_basis' => ['nullable', 'string', 'max:255'],
        ];
    }
}
