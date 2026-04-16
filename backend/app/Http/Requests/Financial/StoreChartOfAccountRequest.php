<?php

namespace App\Http\Requests\Financial;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.chart.create');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)
                ),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts', 'code')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)
                ),
            ],
            'name' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in([
                ChartOfAccount::TYPE_REVENUE,
                ChartOfAccount::TYPE_EXPENSE,
                ChartOfAccount::TYPE_ASSET,
                ChartOfAccount::TYPE_LIABILITY,
            ])],
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O codigo da conta e obrigatório.',
            'code.unique' => 'já existe uma conta com este codigo.',
            'name.required' => 'O nome da conta e obrigatório.',
            'type.required' => 'O tipo da conta e obrigatório.',
            'type.in' => 'Tipo de conta inválido.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
