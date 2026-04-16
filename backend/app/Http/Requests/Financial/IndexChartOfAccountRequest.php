<?php

namespace App\Http\Requests\Financial;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.chart.view');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'type' => ['nullable', 'string', Rule::in([
                ChartOfAccount::TYPE_REVENUE,
                ChartOfAccount::TYPE_EXPENSE,
                ChartOfAccount::TYPE_ASSET,
                ChartOfAccount::TYPE_LIABILITY,
            ])],
            'search' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
