<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.goal.update');
    }

    public function rules(): array
    {
        return [
            'target_amount' => 'sometimes|numeric|min:1',
            'type' => 'sometimes|in:revenue,os_count,new_clients',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'bonus_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
