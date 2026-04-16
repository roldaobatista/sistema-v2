<?php

namespace App\Http\Requests\ServiceCall;

use App\Enums\ServiceCallStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCallStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('resolution_notes') && $this->input('resolution_notes') === '') {
            $this->merge(['resolution_notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(ServiceCallStatus::cases(), 'value'))],
            'resolution_notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O status é obrigatório.',
            'status.in' => 'Status inválido.',
        ];
    }
}
