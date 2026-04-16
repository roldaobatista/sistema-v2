<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RejectLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hr.leave.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->getReason() === '') {
                $validator->errors()->add('reason', 'O motivo da rejeição é obrigatório.');
            }
        });
    }

    public function getReason(): string
    {
        $r = $this->input('reason');
        $rr = $this->input('rejection_reason');

        return trim((string) ($r ?? $rr ?? ''));
    }
}
