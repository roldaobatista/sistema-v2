<?php

namespace App\Http\Requests\Automation;

use App\Models\Lookups\AutomationReportFormat;
use App\Models\Lookups\AutomationReportFrequency;
use App\Models\Lookups\AutomationReportType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledReportRequest extends FormRequest
{
    private const REPORT_TYPE_FALLBACK = [
        'work-orders' => 'Ordens de Servico',
        'productivity' => 'Produtividade',
        'financial' => 'Financeiro',
        'commissions' => 'Comissoes',
        'profitability' => 'Lucratividade',
        'quotes' => 'Orcamentos',
        'service-calls' => 'Chamados',
        'technician-cash' => 'Caixa Tecnico',
        'crm' => 'CRM',
        'equipments' => 'Equipamentos',
        'suppliers' => 'Fornecedores',
        'stock' => 'Estoque',
        'customers' => 'Clientes',
    ];

    private const FREQUENCY_FALLBACK = [
        'daily' => 'Diario',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
    ];

    private const FORMAT_FALLBACK = [
        'pdf' => 'PDF',
        'excel' => 'Excel',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('reports.scheduled.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $allowedTypes = LookupValueResolver::allowedValues(
            AutomationReportType::class,
            self::REPORT_TYPE_FALLBACK,
            $tenantId
        );
        $allowedFrequencies = LookupValueResolver::allowedValues(
            AutomationReportFrequency::class,
            self::FREQUENCY_FALLBACK,
            $tenantId
        );
        $allowedFormats = LookupValueResolver::allowedValues(
            AutomationReportFormat::class,
            self::FORMAT_FALLBACK,
            $tenantId
        );

        return [
            'name' => 'required|string|max:255',
            'report_type' => ['required', 'string', Rule::in($allowedTypes)],
            'frequency' => ['required', 'string', Rule::in($allowedFrequencies)],
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'filters' => 'nullable|array',
            'format' => ['nullable', 'string', Rule::in($allowedFormats)],
        ];
    }
}
