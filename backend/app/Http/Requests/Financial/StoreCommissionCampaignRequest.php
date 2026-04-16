<?php

namespace App\Http\Requests\Financial;

use App\Models\CommissionRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.campaign.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['applies_to_role', 'applies_to_calculation_type'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($this->has('applies_to_role')) {
            $cleaned['applies_to_role'] = CommissionRule::normalizeRole($this->input('applies_to_role'));
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'multiplier' => 'required|numeric|min:1.01|max:5.00',
            'applies_to_role' => 'nullable|in:'.implode(',', CommissionRule::acceptedRoleValues()),
            'applies_to_calculation_type' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'multiplier.required' => 'O multiplicador é obrigatório.',
            'starts_at.required' => 'A data de início é obrigatória.',
            'ends_at.required' => 'A data de término é obrigatória.',
        ];
    }
}
