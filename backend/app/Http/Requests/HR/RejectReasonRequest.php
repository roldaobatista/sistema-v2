<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class RejectReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.adjustment.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'O motivo é obrigatório.',
        ];
    }
}
