<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TimesheetReportRequest;
use App\Models\CustomerComplaint;
use App\Models\FleetVehicle;
use App\Models\QualityProcedure;
use App\Models\SatisfactionSurvey;
use App\Models\TimeClockEntry;
use App\Models\TrafficFine;
use App\Support\QualityActionMetrics;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PeripheralReportController extends Controller
{
    use ResolvesCurrentTenant;

    // ─── HR: Folha de Ponto Mensal ──────────────────────────────

    public function timesheetReport(TimesheetReportRequest $request): Response
    {
        $tenantId = $this->resolvedTenantId();
        $month = Carbon::createFromFormat('Y-m', $request->month);
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        $query = TimeClockEntry::where('tenant_id', $tenantId)
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->with('user:id,name');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $entries = $query->orderBy('user_id')->orderBy('clock_in')->get();

        $grouped = $entries->groupBy('user_id');
        $companyName = $request->user()?->tenant?->name ?? 'Empresa';

        $html = $this->buildTimesheetHtml($grouped, $month, $companyName);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"folha-ponto-{$request->month}.html\"",
        ]);
    }

    private function buildTimesheetHtml($grouped, Carbon $month, string $companyName): string
    {
        $monthName = $month->translatedFormat('F/Y');
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Folha de Ponto - {$monthName}</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
  h1 { text-align: center; font-size: 18px; margin-bottom: 5px; }
  h2 { font-size: 14px; margin-top: 20px; border-bottom: 2px solid #333; padding-bottom: 3px; }
  .meta { text-align: center; color: #666; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
  th, td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; }
  th { background: #f5f5f5; font-weight: bold; }
  .total { font-weight: bold; background: #e8f4fd; }
  .overtime { color: #e53e3e; }
  .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; }
  @media print { body { margin: 0; } .no-print { display: none; } }
</style>
</head>
<body>
<h1>{$companyName}</h1>
<p class="meta">Folha de Ponto — {$monthName}</p>
HTML;

        foreach ($grouped as $userId => $entries) {
            $userName = $entries->first()->user->name ?? 'Colaborador #'.$userId;
            $totalMinutes = 0;
            $workDays = 0;

            $html .= "<h2>{$userName}</h2>";
            $html .= '<table><thead><tr><th>Data</th><th>Entrada</th><th>Saída</th><th>Horas</th><th>Tipo</th><th>Obs.</th></tr></thead><tbody>';

            foreach ($entries as $entry) {
                $clockIn = Carbon::parse($entry->clock_in);
                $clockOut = $entry->clock_out ? Carbon::parse($entry->clock_out) : null;
                $minutes = $clockOut ? $clockIn->diffInMinutes($clockOut) : 0;
                $totalMinutes += $minutes;

                if ($clockOut) {
                    $workDays++;
                }

                $hours = $minutes > 0 ? sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60) : '—';
                $date = $clockIn->format('d/m/Y');
                $inTime = $clockIn->format('H:i');
                $outTime = $clockOut ? $clockOut->format('H:i') : '—';
                $type = $entry->type ?? 'regular';
                $notes = htmlspecialchars($entry->notes ?? '');

                $html .= "<tr><td>{$date}</td><td>{$inTime}</td><td>{$outTime}</td><td>{$hours}</td><td>{$type}</td><td>{$notes}</td></tr>";
            }

            $totalHours = sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
            $expectedHours = $workDays * 8;
            $overtimeMinutes = max(0, $totalMinutes - ($workDays * 480));
            $overtimeHours = sprintf('%02d:%02d', intdiv($overtimeMinutes, 60), $overtimeMinutes % 60);

            $overtimeClass = $overtimeMinutes > 0 ? ' class="overtime"' : '';

            $html .= "<tr class=\"total\"><td colspan=\"3\">Total ({$workDays} dias)</td><td>{$totalHours}</td><td{$overtimeClass}>HE: {$overtimeHours}</td><td></td></tr>";
            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer">Gerado em '.now()->format('d/m/Y H:i').' | KALIBRIUM ERP</div>';
        $html .= '</body></html>';

        return $html;
    }

    // ─── Quality: Relatório de Auditoria ────────────────────────

    public function qualityAuditReport(Request $request): Response
    {
        $tenantId = $this->resolvedTenantId();
        $period = (int) $request->input('period', 6);
        $startDate = now()->subMonths($period)->startOfMonth();

        $procedures = QualityProcedure::where('tenant_id', $tenantId)->get();
        $actions = QualityActionMetrics::actionsForPeriod($tenantId, $startDate);
        $complaints = CustomerComplaint::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->get();
        $surveys = SatisfactionSurvey::where('tenant_id', $tenantId)
            ->whereNotNull('nps_score')
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalProc = $procedures->count();
        $activeProc = $procedures->where('status', 'active')->count();
        $conformity = $totalProc > 0 ? round(($activeProc / $totalProc) * 100, 1) : 0;

        $promoters = $surveys->where('nps_score', '>=', 9)->count();
        $detractors = $surveys->where('nps_score', '<=', 6)->count();
        $nps = $surveys->count() > 0 ? round((($promoters - $detractors) / $surveys->count()) * 100, 1) : 0;

        $companyName = $request->user()?->tenant?->name ?? 'Empresa';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório de Qualidade</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
  h1 { text-align: center; font-size: 18px; }
  h2 { font-size: 14px; margin-top: 20px; border-bottom: 2px solid #333; padding-bottom: 3px; }
  .meta { text-align: center; color: #666; margin-bottom: 20px; }
  .kpi-grid { display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap; }
  .kpi { border: 1px solid #ddd; border-radius: 8px; padding: 12px; text-align: center; flex: 1; min-width: 120px; }
  .kpi-value { font-size: 24px; font-weight: bold; color: #2563eb; }
  .kpi-label { font-size: 11px; color: #666; margin-top: 4px; }
  table { width: 100%; border-collapse: collapse; margin: 10px 0; }
  th, td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; }
  th { background: #f5f5f5; font-weight: bold; }
  .status-open { color: #f59e0b; }
  .status-overdue { color: #ef4444; font-weight: bold; }
  .status-completed { color: #10b981; }
  .nps-positive { color: #10b981; }
  .nps-negative { color: #ef4444; }
  .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; }
  @media print { body { margin: 0; } }
</style>
</head>
<body>
<h1>{$companyName}</h1>
<p class="meta">Relatório de Qualidade — Últimos {$period} meses</p>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi-value">{$conformity}%</div><div class="kpi-label">Índice de Conformidade</div></div>
  <div class="kpi"><div class="kpi-value {$this->npsClass($nps)}">{$nps}</div><div class="kpi-label">NPS Score</div></div>
  <div class="kpi"><div class="kpi-value">{$actions->count()}</div><div class="kpi-label">Ações Corretivas</div></div>
  <div class="kpi"><div class="kpi-value">{$complaints->count()}</div><div class="kpi-label">Reclamações</div></div>
  <div class="kpi"><div class="kpi-value">{$surveys->count()}</div><div class="kpi-label">Pesquisas NPS</div></div>
</div>

<h2>Procedimentos ({$totalProc} total)</h2>
<table><thead><tr><th>Código</th><th>Título</th><th>Status</th><th>Revisão</th><th>Próx. Revisão</th></tr></thead><tbody>
HTML;

        foreach ($procedures->sortBy('code') as $proc) {
            $reviewDue = $proc->review_date
                ? (Carbon::parse($proc->review_date)->isPast() ? '<span class="status-overdue">'.Carbon::parse($proc->review_date)->format('d/m/Y').'</span>' : Carbon::parse($proc->review_date)->format('d/m/Y'))
                : '—';
            $html .= "<tr><td>{$proc->code}</td><td>{$proc->title}</td><td>{$proc->status}</td><td>Rev. {$proc->revision}</td><td>{$reviewDue}</td></tr>";
        }

        $html .= '</tbody></table>';

        $openActions = $actions->whereIn('status', ['open', 'in_progress']);
        if ($openActions->count() > 0) {
            $html .= '<h2>Ações Corretivas Abertas ('.$openActions->count().')</h2>';
            $html .= '<table><thead><tr><th>Descrição</th><th>Tipo</th><th>Responsável</th><th>Prazo</th><th>Status</th></tr></thead><tbody>';

            foreach ($openActions as $action) {
                $deadlineValue = is_array($action) ? ($action['deadline'] ?? null) : $action->deadline;
                $statusValue = is_array($action) ? ($action['status'] ?? null) : $action->status;
                $typeValue = is_array($action) ? ($action['type'] ?? null) : $action->type;
                $descriptionValue = is_array($action) ? ($action['description'] ?? null) : $action->description;
                $responsibleName = is_array($action) ? ($action['responsible_name'] ?? null) : $action->responsible_name;

                $deadline = $deadlineValue ? Carbon::parse($deadlineValue)->format('d/m/Y') : '—';
                $overdue = $deadlineValue && Carbon::parse($deadlineValue)->isPast();
                $statusClass = $overdue ? 'status-overdue' : 'status-open';
                $statusText = $overdue ? 'ATRASADA' : (string) $statusValue;
                $resp = htmlspecialchars((string) ($responsibleName ?? '—'));
                $description = htmlspecialchars((string) ($descriptionValue ?? ''));
                $type = htmlspecialchars((string) ($typeValue ?? ''));
                $html .= "<tr><td>{$description}</td><td>{$type}</td><td>{$resp}</td><td>{$deadline}</td><td class=\"{$statusClass}\">{$statusText}</td></tr>";
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer">Gerado em '.now()->format('d/m/Y H:i').' | KALIBRIUM ERP</div>';
        $html .= '</body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"relatorio-qualidade-{$period}m.html\"",
        ]);
    }

    // ─── Fleet: Relatório de Custos ─────────────────────────────

    public function fleetCostReport(Request $request): Response
    {
        $tenantId = $this->resolvedTenantId();
        $period = (int) $request->input('period', 6);
        $startDate = now()->subMonths($period)->startOfMonth();

        $vehicles = FleetVehicle::where('tenant_id', $tenantId)
            ->where('status', '!=', 'inactive')
            ->get();

        $fuelCosts = DB::table('fuel_logs')
            ->join('fleet_vehicles', 'fuel_logs.fleet_vehicle_id', '=', 'fleet_vehicles.id')
            ->where('fleet_vehicles.tenant_id', $tenantId)
            ->where('fuel_logs.created_at', '>=', $startDate)
            ->groupBy('fleet_vehicles.id', 'fleet_vehicles.plate', 'fleet_vehicles.brand', 'fleet_vehicles.model')
            ->select(
                'fleet_vehicles.id',
                'fleet_vehicles.plate',
                DB::raw("CONCAT(fleet_vehicles.brand, ' ', fleet_vehicles.model) as vehicle"),
                DB::raw('COALESCE(SUM(fuel_logs.total_cost), 0) as fuel_cost'),
                DB::raw('COALESCE(SUM(fuel_logs.liters), 0) as total_liters'),
                DB::raw('COUNT(fuel_logs.id) as fueling_count')
            )
            ->orderByDesc('fuel_cost')
            ->get();

        $fines = TrafficFine::where('tenant_id', $tenantId)
            ->where('fine_date', '>=', $startDate)
            ->with('vehicle:id,plate')
            ->get();

        $totalFuelCost = $fuelCosts->sum('fuel_cost');
        $totalFineCost = $fines->sum('amount');
        $totalCost = $totalFuelCost + $totalFineCost;

        $companyName = $request->user()?->tenant?->name ?? 'Empresa';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório de Custos da Frota</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
  h1 { text-align: center; font-size: 18px; }
  h2 { font-size: 14px; margin-top: 20px; border-bottom: 2px solid #333; padding-bottom: 3px; }
  .meta { text-align: center; color: #666; margin-bottom: 20px; }
  .kpi-grid { display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap; }
  .kpi { border: 1px solid #ddd; border-radius: 8px; padding: 12px; text-align: center; flex: 1; min-width: 120px; }
  .kpi-value { font-size: 24px; font-weight: bold; color: #2563eb; }
  .kpi-label { font-size: 11px; color: #666; margin-top: 4px; }
  table { width: 100%; border-collapse: collapse; margin: 10px 0; }
  th, td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; }
  th { background: #f5f5f5; font-weight: bold; }
  .text-right { text-align: right; }
  .total-row { font-weight: bold; background: #e8f4fd; }
  .alert { color: #ef4444; }
  .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; }
  @media print { body { margin: 0; } }
</style>
</head>
<body>
<h1>{$companyName}</h1>
<p class="meta">Relatório de Custos da Frota — Últimos {$period} meses</p>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi-value">{$vehicles->count()}</div><div class="kpi-label">Veículos Ativos</div></div>
  <div class="kpi"><div class="kpi-value">R$ {$this->formatMoney($totalFuelCost)}</div><div class="kpi-label">Combustível</div></div>
  <div class="kpi"><div class="kpi-value">R$ {$this->formatMoney($totalFineCost)}</div><div class="kpi-label">Multas</div></div>
  <div class="kpi"><div class="kpi-value">R$ {$this->formatMoney($totalCost)}</div><div class="kpi-label">Custo Total</div></div>
</div>

<h2>Custo de Combustível por Veículo</h2>
<table><thead><tr><th>Placa</th><th>Veículo</th><th>Abastecimentos</th><th class="text-right">Litros</th><th class="text-right">Custo</th></tr></thead><tbody>
HTML;

        foreach ($fuelCosts as $row) {
            $html .= "<tr><td>{$row->plate}</td><td>{$row->vehicle}</td><td>{$row->fueling_count}</td><td class=\"text-right\">".number_format($row->total_liters, 1, ',', '.')."</td><td class=\"text-right\">R$ {$this->formatMoney($row->fuel_cost)}</td></tr>";
        }

        $html .= '<tr class="total-row"><td colspan="3">TOTAL</td><td class="text-right">'.number_format($fuelCosts->sum('total_liters'), 1, ',', '.')."</td><td class=\"text-right\">R$ {$this->formatMoney($totalFuelCost)}</td></tr>";
        $html .= '</tbody></table>';

        // Multas
        if ($fines->count() > 0) {
            $html .= '<h2>Multas ('.$fines->count().')</h2>';
            $html .= '<table><thead><tr><th>Data</th><th>Placa</th><th>Descrição</th><th>Status</th><th class="text-right">Valor</th></tr></thead><tbody>';

            foreach ($fines->sortByDesc('fine_date') as $fine) {
                $date = $fine->fine_date ? Carbon::parse($fine->fine_date)->format('d/m/Y') : '—';
                $plate = $fine->vehicle->plate ?? '—';
                $desc = htmlspecialchars($fine->description ?? '');
                $html .= "<tr><td>{$date}</td><td>{$plate}</td><td>{$desc}</td><td>{$fine->status}</td><td class=\"text-right\">R$ {$this->formatMoney((float) ($fine->amount ?? 0))}</td></tr>";
            }

            $html .= "<tr class=\"total-row\"><td colspan=\"4\">TOTAL MULTAS</td><td class=\"text-right\">R$ {$this->formatMoney($totalFineCost)}</td></tr>";
            $html .= '</tbody></table>';
        }

        // Alertas de vencimento
        $expiring = $vehicles->filter(fn ($v) => ($v->crlv_expiry && Carbon::parse($v->crlv_expiry)->lte(now()->addMonth()))
            || ($v->insurance_expiry && Carbon::parse($v->insurance_expiry)->lte(now()->addMonth()))
            || ($v->next_maintenance && Carbon::parse($v->next_maintenance)->lte(now())));

        if ($expiring->count() > 0) {
            $html .= '<h2 class="alert">Alertas de Vencimento ('.$expiring->count().')</h2>';
            $html .= '<table><thead><tr><th>Placa</th><th>Veículo</th><th>CRLV</th><th>Seguro</th><th>Manutenção</th></tr></thead><tbody>';

            foreach ($expiring as $v) {
                $crlv = $v->crlv_expiry ? Carbon::parse($v->crlv_expiry)->format('d/m/Y') : '—';
                $ins = $v->insurance_expiry ? Carbon::parse($v->insurance_expiry)->format('d/m/Y') : '—';
                $maint = $v->next_maintenance ? Carbon::parse($v->next_maintenance)->format('d/m/Y') : '—';
                $html .= "<tr><td>{$v->plate}</td><td>{$v->brand} {$v->model}</td><td>{$crlv}</td><td>{$ins}</td><td>{$maint}</td></tr>";
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer">Gerado em '.now()->format('d/m/Y H:i').' | KALIBRIUM ERP</div>';
        $html .= '</body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"custos-frota-{$period}m.html\"",
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function npsClass(float $nps): string
    {
        return $nps >= 0 ? 'nps-positive' : 'nps-negative';
    }
}
