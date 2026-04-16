<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class AnswerVisitSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ];
    }
}
