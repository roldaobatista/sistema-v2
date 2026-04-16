<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionGoalRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('commissions.goal.create');
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                $this->tenantUserExistsRule(),
            ],
            'period' => ['required', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
            'target_amount' => 'required|numeric|min:1',
            'type' => 'sometimes|in:revenue,os_count,new_clients',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'bonus_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
