<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmLeadScoringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.scoring.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'field' => 'string|max:100',
            'operator' => [Rule::in(CrmLeadScoringRule::OPERATORS)],
            'value' => 'string|max:500',
            'points' => 'integer|min:-100|max:100',
            'category' => [Rule::in(array_keys(CrmLeadScoringRule::CATEGORIES))],
            'is_active' => 'boolean',
        ];
    }
}
