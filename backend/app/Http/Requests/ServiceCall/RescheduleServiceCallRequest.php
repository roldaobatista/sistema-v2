<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleServiceCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && $this->input('reason') === '') {
            $this->merge(['reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => 'required|date|after:now',
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_date.required' => 'A nova data de agendamento é obrigatória.',
            'scheduled_date.after' => 'A data deve ser futura.',
            'reason.required' => 'O motivo do reagendamento é obrigatório.',
        ];
    }
}
