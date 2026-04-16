<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class ForwardEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.send');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && $this->input('body') === '') {
            $this->merge(['body' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'to' => 'required|string',
            'body' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'to.required' => 'O destinatário é obrigatório.',
        ];
    }
}
