<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmDealCompetitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmDealCompetitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'competitor_name' => 'string|max:255',
            'competitor_price' => 'nullable|numeric|min:0',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'outcome' => [Rule::in(array_keys(CrmDealCompetitor::OUTCOMES))],
        ];
    }
}
