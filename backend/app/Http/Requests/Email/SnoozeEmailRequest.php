<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class SnoozeEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('snoozed_until') && $this->input('snoozed_until') === '') {
            $this->merge(['snoozed_until' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'snoozed_until' => 'nullable|date|after:now',
        ];
    }
}
