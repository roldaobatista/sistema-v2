<?php

namespace App\Http\Requests\Financial;

use App\Models\CommissionEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionEventStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.event.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(CommissionEvent::STATUSES))],
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O status é obrigatório.',
        ];
    }
}
