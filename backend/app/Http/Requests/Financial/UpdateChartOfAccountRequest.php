<?php

namespace App\Http\Requests\Financial;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.chart.update');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $accountId = (int) $this->route('id');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)
                ),
            ],
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($accountId),
            ],
            'name' => 'string|max:255',
            'type' => ['string', Rule::in([
                ChartOfAccount::TYPE_REVENUE,
                ChartOfAccount::TYPE_EXPENSE,
                ChartOfAccount::TYPE_ASSET,
                ChartOfAccount::TYPE_LIABILITY,
            ])],
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'já existe uma conta com este codigo.',
            'type.in' => 'Tipo de conta inválido.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
