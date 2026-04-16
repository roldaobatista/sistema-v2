<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSatisfactionSurveyResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public portal endpoint
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['comment', 'service_rating', 'technician_rating', 'timeliness_rating', 'nps_score'];
        $cleaned = [];

        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }

        if ($cleaned !== []) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'nps_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'service_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'technician_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'timeliness_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasAnswer = $this->filled('comment')
                || $this->filled('nps_score')
                || $this->filled('service_rating')
                || $this->filled('technician_rating')
                || $this->filled('timeliness_rating');

            if (! $hasAnswer) {
                $validator->errors()->add('nps_score', 'Informe ao menos uma avaliação para responder a pesquisa.');
            }
        });
    }
}
