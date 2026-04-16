<?php

namespace App\Services;

use App\Models\PayrollLine;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Generates printable HTML payslips (holerites).
 * Can be converted to PDF via dompdf if available.
 */
class PayslipPdfService
{
    public function generateHtml(PayrollLine $line): string
    {
        $line->load(['user', 'payroll']);
        $user = $line->user;
        $payroll = $line->payroll;
        $tenant = Tenant::find($payroll->tenant_id);

        $referenceMonth = $payroll->reference_month;
        $monthLabel = Carbon::parse($referenceMonth.'-01')
            ->locale('pt_BR')
            ->isoFormat('MMMM/YYYY');

        $earnings = $this->buildEarnings($line);
        $deductions = $this->buildDeductions($line);

        $totalEarnings = array_sum(array_column($earnings, 'value'));
        $totalDeductions = array_sum(array_column($deductions, 'value'));

        return $this->renderTemplate([
            'tenant_name' => $tenant->name ?? '',
            'tenant_document' => $tenant->document ?? '',
            'employee_name' => $user->name ?? '',
            'employee_cpf' => $user->cpf ?? '',
            'employee_pis' => $user->pis_number ?? '',
            'employee_ctps' => ($user->ctps_number ?? '').'/'.($user->ctps_series ?? ''),
            'admission_date' => $user->admission_date ? Carbon::parse($user->admission_date)->format('d/m/Y') : '',
            'cbo' => $user->cbo_code ?? '',
            'reference_month' => mb_strtoupper($monthLabel),
            'earnings' => $earnings,
            'deductions' => $deductions,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => (float) $line->net_salary,
            'fgts_base' => (float) $line->gross_salary,
            'fgts_value' => (float) $line->fgts_value,
            'inss_base' => (float) $line->gross_salary,
            'irrf_base' => (float) $line->gross_salary - (float) $line->inss_employee,
        ]);
    }

    private function buildEarnings(PayrollLine $line): array
    {
        $items = [];

        $this->addItem($items, 'Salario Base', (float) $line->base_salary);
        $this->addItem($items, 'Horas Extras 50%', (float) $line->overtime_50_value, $line->overtime_50_hours.'h');
        $this->addItem($items, 'Horas Extras 100%', (float) $line->overtime_100_value, $line->overtime_100_hours.'h');
        $this->addItem($items, 'Adicional Noturno', (float) $line->night_shift_value, $line->night_hours.'h');
        $this->addItem($items, 'DSR s/ Horas Extras', (float) $line->dsr_value);
        $this->addItem($items, 'Comissoes', (float) $line->commission_value);
        $this->addItem($items, 'Bonus/Gratificacoes', (float) ($line->bonus_value ?? 0));
        $this->addItem($items, 'Outros Proventos', (float) ($line->other_earnings ?? 0));

        return $items;
    }

    private function buildDeductions(PayrollLine $line): array
    {
        $items = [];

        $this->addItem($items, 'INSS', (float) $line->inss_employee);
        $this->addItem($items, 'IRRF', (float) $line->irrf);
        $this->addItem($items, 'Vale Transporte', (float) $line->transportation_discount);
        $this->addItem($items, 'Vale Refeicao/Alimentacao', (float) $line->meal_discount);
        $this->addItem($items, 'Plano de Saude', (float) $line->health_insurance_discount);
        $this->addItem($items, 'Adiantamento', (float) ($line->advance_discount ?? 0));
        $this->addItem($items, 'Outros Descontos', (float) $line->other_deductions);
        $this->addItem($items, 'Faltas/Atrasos', (float) ($line->absence_value ?? 0));

        return $items;
    }

    private function addItem(array &$items, string $label, float $value, ?string $reference = null): void
    {
        if ($value > 0) {
            $items[] = [
                'label' => $label,
                'value' => $value,
                'reference' => $reference,
            ];
        }
    }

    private function renderTemplate(array $data): string
    {
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');

        $earningsRows = '';
        foreach ($data['earnings'] as $item) {
            $ref = $item['reference'] ? "<span style='color:#666;font-size:11px'>({$item['reference']})</span>" : '';
            $earningsRows .= "<tr><td style='padding:4px 8px;border-bottom:1px solid #eee'>{$item['label']} {$ref}</td><td style='padding:4px 8px;text-align:right;border-bottom:1px solid #eee'>R$ {$fmt($item['value'])}</td></tr>";
        }

        $deductionRows = '';
        foreach ($data['deductions'] as $item) {
            $deductionRows .= "<tr><td style='padding:4px 8px;border-bottom:1px solid #eee'>{$item['label']}</td><td style='padding:4px 8px;text-align:right;border-bottom:1px solid #eee'>R$ {$fmt($item['value'])}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Holerite - {$data['reference_month']}</title>
<style>
    body { font-family: Arial, sans-serif; font-size: 13px; color: #333; margin: 20px; }
    .header { border: 2px solid #333; padding: 12px; margin-bottom: 10px; }
    .header h2 { margin: 0 0 4px 0; font-size: 16px; }
    .header p { margin: 2px 0; font-size: 12px; color: #555; }
    .employee-info { display: flex; flex-wrap: wrap; gap: 20px; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
    .employee-info div { flex: 1; min-width: 150px; }
    .employee-info label { font-size: 10px; color: #888; display: block; }
    .employee-info span { font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th { background: #f5f5f5; padding: 6px 8px; text-align: left; border-bottom: 2px solid #ccc; font-size: 12px; }
    .totals { border-top: 2px solid #333; padding: 8px; display: flex; justify-content: space-between; font-weight: bold; }
    .net { background: #e8f5e9; border: 2px solid #2e7d32; padding: 12px; text-align: center; margin: 10px 0; }
    .net .amount { font-size: 24px; font-weight: bold; color: #2e7d32; }
    .footer { display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-top: 15px; padding-top: 10px; border-top: 1px solid #ccc; }
    @media print { body { margin: 0; } }
</style>
</head>
<body>
<div class="header">
    <h2>{$data['tenant_name']}</h2>
    <p>CNPJ: {$data['tenant_document']}</p>
    <p style="font-weight:bold;font-size:14px">DEMONSTRATIVO DE PAGAMENTO - {$data['reference_month']}</p>
</div>

<div class="employee-info">
    <div><label>Nome</label><span>{$data['employee_name']}</span></div>
    <div><label>CPF</label><span>{$data['employee_cpf']}</span></div>
    <div><label>PIS</label><span>{$data['employee_pis']}</span></div>
    <div><label>CTPS</label><span>{$data['employee_ctps']}</span></div>
    <div><label>Admissao</label><span>{$data['admission_date']}</span></div>
    <div><label>CBO</label><span>{$data['cbo']}</span></div>
</div>

<table>
    <thead><tr><th colspan="2">PROVENTOS</th></tr></thead>
    <tbody>{$earningsRows}</tbody>
    <tfoot><tr style="font-weight:bold;background:#f0f0f0"><td style="padding:6px 8px">TOTAL PROVENTOS</td><td style="padding:6px 8px;text-align:right">R$ {$fmt($data['total_earnings'])}</td></tr></tfoot>
</table>

<table>
    <thead><tr><th colspan="2">DESCONTOS</th></tr></thead>
    <tbody>{$deductionRows}</tbody>
    <tfoot><tr style="font-weight:bold;background:#fef0f0"><td style="padding:6px 8px">TOTAL DESCONTOS</td><td style="padding:6px 8px;text-align:right">R$ {$fmt($data['total_deductions'])}</td></tr></tfoot>
</table>

<div class="net">
    <div style="font-size:12px;color:#555">LIQUIDO A RECEBER</div>
    <div class="amount">R$ {$fmt($data['net_salary'])}</div>
</div>

<div class="footer">
    <div>FGTS Base: R$ {$fmt($data['fgts_base'])} | FGTS Deposito: R$ {$fmt($data['fgts_value'])}</div>
    <div>INSS Base: R$ {$fmt($data['inss_base'])} | IRRF Base: R$ {$fmt($data['irrf_base'])}</div>
</div>
</body>
</html>
HTML;
    }
}
