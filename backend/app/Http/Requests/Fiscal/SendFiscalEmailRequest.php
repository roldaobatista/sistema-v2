<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class SendFiscalEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.view');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['email', 'message'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'email' => 'nullable|email|max:255',
            'message' => 'nullable|string|max:5000',
        ];
    }
}
