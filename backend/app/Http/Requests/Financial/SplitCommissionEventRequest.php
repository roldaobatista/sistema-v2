<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;

class SplitCommissionEventRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('commissions.event.update');
    }

    public function rules(): array
    {
        return [
            'splits' => 'required|array|min:2',
            'splits.*.user_id' => ['required', $this->tenantUserExistsRule()],
            'splits.*.percentage' => 'required|numeric|min:0.01|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'splits.required' => 'Informe ao menos dois rateios.',
            'splits.min' => 'É necessário ao menos dois rateios.',
        ];
    }
}
