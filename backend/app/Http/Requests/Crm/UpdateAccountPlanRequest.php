<?php

namespace App\Http\Requests\Crm;

use App\Models\AccountPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'objective' => 'nullable|string',
            'status' => [Rule::in(array_keys(AccountPlan::STATUSES))],
            'target_date' => 'nullable|date',
            'revenue_target' => 'nullable|numeric|min:0',
            'revenue_current' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
