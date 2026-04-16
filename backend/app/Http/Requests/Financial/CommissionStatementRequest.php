<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;

class CommissionStatementRequest extends FormRequest
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
            'period' => ['required', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O usuário é obrigatório.',
            'period.required' => 'O período (YYYY-MM) é obrigatório.',
        ];
    }
}
