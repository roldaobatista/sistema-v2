<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.onboarding.manage');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:in_progress,completed,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O status é obrigatório.',
        ];
    }
}
