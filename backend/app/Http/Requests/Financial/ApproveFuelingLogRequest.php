<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveFuelingLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.fueling_log.approve');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rejection_reason' => $this->rejection_reason === '' ? null : $this->rejection_reason,
        ]);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
