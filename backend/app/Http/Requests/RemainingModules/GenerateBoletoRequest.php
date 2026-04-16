<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateBoletoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'receivable_id' => ['required', Rule::exists('accounts_receivable', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
