<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;

class CommissionBalanceSummaryRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('commissions.settlement.view');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', $this->tenantUserExistsRule()],
        ];
    }
}
