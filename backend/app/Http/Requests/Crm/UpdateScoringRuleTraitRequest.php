<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScoringRuleTraitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.scoring.manage');
    }

    public function rules(): array
    {
        return [
            'category' => 'sometimes|string',
            'field' => 'sometimes|string',
            'operator' => 'sometimes|string',
            'value' => 'sometimes',
            'points' => 'sometimes|integer|between:-100,100',
            'description' => 'nullable|string|max:500',
        ];
    }
}
