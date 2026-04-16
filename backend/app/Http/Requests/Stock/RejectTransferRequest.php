<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class RejectTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.transfer.accept');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejection_reason') && $this->input('rejection_reason') === '') {
            $this->merge(['rejection_reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
