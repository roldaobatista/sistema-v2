<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmSalesGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.goal.manage');
    }

    public function rules(): array
    {
        return [
            'target_revenue' => 'numeric|min:0',
            'target_deals' => 'integer|min:0',
            'target_new_customers' => 'integer|min:0',
            'target_activities' => 'integer|min:0',
        ];
    }
}
