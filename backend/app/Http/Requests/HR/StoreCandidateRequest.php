<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.recruitment.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['phone', 'resume_path', 'notes'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'resume_path' => 'nullable|string',
            'stage' => 'required|in:applied,screening,interview,technical_test,offer,hired,rejected',
            'notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do candidato é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'stage.required' => 'O estágio do candidato é obrigatório.',
        ];
    }
}
