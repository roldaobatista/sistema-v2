<?php

namespace App\Http\Requests\RepairSeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportSealDamageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('repair_seals.use');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['damaged', 'lost'])],
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Justificativa obrigatória para selo danificado ou perdido.',
        ];
    }
}
