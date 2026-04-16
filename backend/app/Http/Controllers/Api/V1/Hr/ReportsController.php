<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\HrReportRequest;
use App\Models\JourneyEntry;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * GET /hr/reports/overtime-trend — Tendência de horas extras por mês.
     */
    public function overtimeTrend(HrReportRequest $request): JsonResponse
    {
        try {
            $months = $request->integer('months', 12);
            $startDate = now()->subMonths($months)->startOfMonth()->toDateString();

            $isMySQL = DB::connection()->getDriverName() === 'mysql';
            $monthExpr = $isMySQL ? "DATE_FORMAT(date, '%Y-%m')" : "strftime('%Y-%m', date)";

            $data = JourneyEntry::where('date', '>=', $startDate)
                ->selectRaw("
                    {$monthExpr} as month,
                    SUM(overtime_hours_50) as total_ot50_hours,
                    SUM(overtime_hours_100) as total_ot100_hours,
                    SUM(overtime_hours_50 + overtime_hours_100) as total_overtime_hours,
                    COUNT(DISTINCT user_id) as employees_with_overtime
                ")
                ->groupByRaw($monthExpr)
                ->orderBy('month')
                ->get();

            // Top funcionários com mais HE
            $topOvertime = JourneyEntry::where('date', '>=', $startDate)
                ->selectRaw('user_id, SUM(overtime_hours_50 + overtime_hours_100) as total_hours')
                ->groupBy('user_id')
                ->having('total_hours', '>', 0)
                ->orderByDesc('total_hours')
                ->limit(10)
                ->with('user:id,name,department_id')
                ->get()
                ->map(fn ($entry) => [
                    'user_id' => $entry->user_id,
                    'name' => $entry->user->name ?? 'N/A',
                    'total_hours' => round((float) $entry->total_hours, 2),
                ]);

            return ApiResponse::data([
                'monthly_trend' => $data,
                'top_overtime_employees' => $topOvertime,
            ]);
        } catch (\Exception $e) {
            Log::error('Overtime trend report failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de horas extras.', 500);
        }
    }

    /**
     * GET /hr/reports/hour-bank-forecast — Previsão de expiração de banco de horas.
     */
    public function hourBankForecast(HrReportRequest $request): JsonResponse
    {
        try {
            $balances = DB::table('hour_bank_transactions')
                ->select('user_id', DB::raw('SUM(hours) as current_balance'))
                ->where('tenant_id', $this->resolvedTenantId())
                ->groupBy('user_id')
                ->having('current_balance', '!=', 0)
                ->get();

            $users = User::whereIn('id', $balances->pluck('user_id'))
                ->select('id', 'name', 'salary')
                ->get()
                ->keyBy('id');

            $forecast = $balances->map(function ($balance) use ($users) {
                $user = $users->get($balance->user_id);
                $hourlyRate = $user && $user->salary ? round($user->salary / 220, 2) : 0;

                return [
                    'user_id' => $balance->user_id,
                    'name' => $user->name ?? 'N/A',
                    'balance_hours' => round((float) $balance->current_balance, 2),
                    'financial_value' => round((float) $balance->current_balance * $hourlyRate, 2),
                    'hourly_rate' => $hourlyRate,
                ];
            })->sortByDesc('balance_hours')->values();

            return ApiResponse::data($forecast);
        } catch (\Exception $e) {
            Log::error('Hour bank forecast failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar previsão de banco de horas.', 500);
        }
    }

    /**
     * GET /hr/reports/tax-obligations — Obrigações tributárias.
     */
    public function taxObligations(HrReportRequest $request): JsonResponse
    {
        try {
            $referenceMonth = $request->input('reference_month', now()->format('Y-m'));

            $payroll = Payroll::where('reference_month', $referenceMonth)
                ->where('type', 'regular')
                ->first();

            if (! $payroll) {
                return ApiResponse::data([
                    'reference_month' => $referenceMonth,
                    'message' => 'Nenhuma folha encontrada para este mês.',
                ]);
            }

            $lines = PayrollLine::where('payroll_id', $payroll->id)->get();

            $data = [
                'reference_month' => $referenceMonth,
                'payroll_status' => $payroll->status,
                'employee_count' => $lines->count(),
                'total_gross' => round($lines->sum('gross_salary'), 2),
                'total_net' => round($lines->sum('net_salary'), 2),
                'inss_employee_total' => round($lines->sum('inss_employee'), 2),
                'inss_employer_total' => round($lines->sum('inss_employer_value'), 2),
                'irrf_total' => round($lines->sum('irrf'), 2),
                'fgts_total' => round($lines->sum('fgts_value'), 2),
                'total_labor_cost' => round(
                    $lines->sum('gross_salary') + $lines->sum('fgts_value') + $lines->sum('inss_employer_value'),
                    2
                ),
            ];

            // Acumulado anual
            $yearStart = Carbon::parse($referenceMonth.'-01')->startOfYear()->format('Y-m');
            $yearPayrolls = Payroll::where('reference_month', '>=', $yearStart)
                ->where('reference_month', '<=', $referenceMonth)
                ->whereIn('status', ['calculated', 'approved', 'paid'])
                ->pluck('id');

            $yearLines = PayrollLine::whereIn('payroll_id', $yearPayrolls)->get();

            $data['year_accumulated'] = [
                'total_gross' => round($yearLines->sum('gross_salary'), 2),
                'total_inss_employer' => round($yearLines->sum('inss_employer_value'), 2),
                'total_fgts' => round($yearLines->sum('fgts_value'), 2),
                'total_irrf' => round($yearLines->sum('irrf'), 2),
                'total_labor_cost' => round(
                    $yearLines->sum('gross_salary') + $yearLines->sum('fgts_value') + $yearLines->sum('inss_employer_value'),
                    2
                ),
            ];

            return ApiResponse::data($data);
        } catch (\Exception $e) {
            Log::error('Tax obligations report failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de obrigações tributárias.', 500);
        }
    }

    /**
     * GET /hr/reports/income-statement/{userId}/{year} — Informe de rendimentos.
     */
    public function incomeStatement(int $userId, int $year): JsonResponse
    {
        try {
            $user = User::select('id', 'name', 'cpf', 'admission_date')->findOrFail($userId);

            $yearStart = "{$year}-01";
            $yearEnd = "{$year}-12";

            $payrollIds = Payroll::where('reference_month', '>=', $yearStart)
                ->where('reference_month', '<=', $yearEnd)
                ->whereIn('status', ['calculated', 'approved', 'paid'])
                ->pluck('id');

            $lines = PayrollLine::where('user_id', $userId)
                ->whereIn('payroll_id', $payrollIds)
                ->get();

            if ($lines->isEmpty()) {
                return ApiResponse::data([
                    'user' => $user,
                    'year' => $year,
                    'message' => 'Nenhum registro de folha encontrado para este ano.',
                ]);
            }

            $data = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'cpf' => $user->cpf,
                ],
                'year' => $year,
                'total_gross_income' => round($lines->sum('gross_salary'), 2),
                'total_inss_deducted' => round($lines->sum('inss_employee'), 2),
                'total_irrf_deducted' => round($lines->sum('irrf'), 2),
                'total_thirteenth' => round($lines->sum('thirteenth_value'), 2),
                'total_vacation' => round($lines->sum('vacation_value'), 2),
                'total_vacation_bonus' => round($lines->sum('vacation_bonus'), 2),
                'total_fgts_deposited' => round($lines->sum('fgts_value'), 2),
                'monthly_breakdown' => $lines->groupBy(fn ($line) => $line->payroll?->reference_month ?? 'unknown')
                    ->map(fn ($group) => [
                        'gross' => round($group->sum('gross_salary'), 2),
                        'inss' => round($group->sum('inss_employee'), 2),
                        'irrf' => round($group->sum('irrf'), 2),
                        'net' => round($group->sum('net_salary'), 2),
                    ]),
            ];

            return ApiResponse::data($data);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Colaborador não encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('Income statement report failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar informe de rendimentos.', 500);
        }
    }

    /**
     * GET /hr/reports/labor-cost-by-project — Custo de mão de obra por projeto/OS.
     */
    public function laborCostByProject(HrReportRequest $request): JsonResponse
    {
        try {
            $query = TimeClockEntry::whereNotNull('work_order_id')
                ->whereNotNull('clock_out');

            if ($request->filled('start_date')) {
                $query->whereDate('clock_in', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('clock_in', '<=', $request->input('end_date'));
            }

            $entries = $query->get();

            // Pre-load user salaries to avoid N+1
            $userIds = $entries->pluck('user_id')->unique();
            $users = User::whereIn('id', $userIds)
                ->select('id', 'name', 'salary')
                ->get()
                ->keyBy('id');

            $grouped = $entries->groupBy('work_order_id');

            $result = $grouped->map(function ($woEntries, $workOrderId) use ($users) {
                $totalMinutes = 0;
                $totalCost = 0;
                $employeeIds = [];

                foreach ($woEntries as $entry) {
                    $minutes = $entry->clock_in->diffInMinutes($entry->clock_out);
                    $totalMinutes += $minutes;

                    $user = $users->get($entry->user_id);
                    $hourlyRate = $user && $user->salary ? $user->salary / 220 : 0;
                    $totalCost += ($minutes / 60) * $hourlyRate;

                    $employeeIds[$entry->user_id] = true;
                }

                return [
                    'work_order_id' => (int) $workOrderId,
                    'total_hours' => round($totalMinutes / 60, 2),
                    'total_cost' => round($totalCost, 2),
                    'employee_count' => count($employeeIds),
                ];
            })->sortByDesc('total_cost')->values();

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('Labor cost by project report failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de custo de mão de obra por projeto.', 500);
        }
    }
}
