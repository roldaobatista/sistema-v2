<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;

class CommissionBatchGenerateRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('commissions.rule.create');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', $this->tenantUserExistsRule()],
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ];
    }
}
