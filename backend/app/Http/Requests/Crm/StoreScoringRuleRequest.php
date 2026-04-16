<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmLeadScoringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.scoring.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'field' => 'required|string|max:100',
            'operator' => ['required', Rule::in(CrmLeadScoringRule::OPERATORS)],
            'value' => 'required|string|max:500',
            'points' => 'required|integer|min:-100|max:100',
            'category' => ['required', Rule::in(array_keys(CrmLeadScoringRule::CATEGORIES))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da regra é obrigatório.',
            'field.required' => 'O campo é obrigatório.',
            'operator.required' => 'O operador é obrigatório.',
            'value.required' => 'O valor é obrigatório.',
            'points.required' => 'Os pontos são obrigatórios.',
            'category.required' => 'A categoria é obrigatória.',
        ];
    }
}
