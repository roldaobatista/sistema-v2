<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('disposal_certificate') && $this->input('disposal_certificate') === '') {
            $this->merge(['disposal_certificate' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:pending,approved,in_progress,completed,cancelled',
            'disposal_certificate' => 'nullable|string|max:500',
        ];
    }
}
