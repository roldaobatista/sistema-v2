<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create') || $this->user()->can('finance.payable.create');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'file' => ['required', 'file', 'mimes:ofx,txt,ret,rem', 'max:10240'],
            'bank_account_id' => [
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
