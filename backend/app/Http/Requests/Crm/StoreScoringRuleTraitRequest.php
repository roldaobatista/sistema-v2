<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoringRuleTraitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.scoring.manage');
    }

    public function rules(): array
    {
        return [
            'category' => 'required|string',
            'field' => 'required|string',
            'operator' => 'required|string',
            'value' => 'required',
            'points' => 'required|integer|between:-100,100',
            'description' => 'nullable|string|max:500',
        ];
    }
}
