<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Enums\EquipmentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Enums\QuoteStatus;
use App\Enums\ServiceCallStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsDateRangeRequest;
use App\Http\Requests\Analytics\AnalyticsForecastRequest;
use App\Http\Requests\Analytics\AnalyticsNlQueryRequest;
use App\Http\Requests\Analytics\AnalyticsTrendsRequest;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user !== null, 401, 'Autenticação necessária.');
        $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id);
        abort_unless($tenantId > 0, 403, 'Tenant não definido.');

        return $tenantId;
    }

    /**
     * Resumo executivo cross-module: KPIs consolidados de todos os módulos.
     */
    public function executiveSummary(AnalyticsDateRangeRequest $request): JsonResponse
    {
        try {
            $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
            $to = $request->input('to', Carbon::now()->endOfMonth()->toDateString());
            $tenantId = $this->tenantId($request);

            // ── OS ──
            $osQuery = WorkOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, Carbon::parse($to)->endOfDay()]);

            $totalOs = (clone $osQuery)->count();
            $osCompleted = (clone $osQuery)->where('status', WorkOrder::STATUS_COMPLETED)->count();
            $osPending = (clone $osQuery)->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED])->count();
            $osCancelled = (clone $osQuery)->where('status', WorkOrder::STATUS_CANCELLED)->count();

            // ── Financeiro ──
            $arQuery = AccountReceivable::where('tenant_id', $tenantId)
                ->whereIn('status', [
                    FinancialStatus::PENDING->value,
                    FinancialStatus::PARTIAL->value,
                    FinancialStatus::OVERDUE->value,
                ])
                ->whereBetween('due_date', [$from, $to]);

            $totalReceivable = (clone $arQuery)->sum(DB::raw('amount - amount_paid'));
            $totalReceived = floatval(bcadd(
                $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $from, $to),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $from, $to),
                2
            ));
            $totalOverdue = AccountReceivable::where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value, FinancialStatus::OVERDUE->value])
                ->where('due_date', '<', Carbon::now())
                ->sum(DB::raw('amount - amount_paid'));

            $apQuery = AccountPayable::where('tenant_id', $tenantId)
                ->whereIn('status', [
                    FinancialStatus::PENDING->value,
                    FinancialStatus::PARTIAL->value,
                    FinancialStatus::OVERDUE->value,
                ])
                ->whereBetween('due_date', [$from, $to]);

            $totalPayable = (clone $apQuery)->sum(DB::raw('amount - amount_paid'));
            $totalPaid = floatval(bcadd(
                $this->sumPaymentsForPeriod(AccountPayable::class, $tenantId, $from, $to),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $from, $to),
                2
            ));

            // ── Despesas ──
            $totalExpenses = Expense::where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [$from, $to])
                ->where('status', ExpenseStatus::APPROVED)
                ->sum('amount');

            // ── Orçamentos ──
            $quotesQuery = Quote::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, Carbon::parse($to)->endOfDay()]);

            $totalQuotes = (clone $quotesQuery)->count();
            $approvedQuotes = (clone $quotesQuery)->where('status', QuoteStatus::APPROVED)->count();
            $conversionRate = $totalQuotes > 0 ? round(($approvedQuotes / $totalQuotes) * 100, 1) : 0;
            $quotesValue = (clone $quotesQuery)->sum('total');

            // ── Chamados ──
            $scQuery = ServiceCall::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, Carbon::parse($to)->endOfDay()]);

            $totalServiceCalls = (clone $scQuery)->count();
            $scCompleted = (clone $scQuery)->where('status', ServiceCallStatus::CONVERTED_TO_OS)->count();

            // ── Clientes ──
            $newCustomers = Customer::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, Carbon::parse($to)->endOfDay()])
                ->count();

            $totalCustomers = Customer::where('tenant_id', $tenantId)
                ->where('active', true)
                ->count();

            // ── Equipamentos ──
            $totalEquipments = Equipment::where('tenant_id', $tenantId)
                ->where('status', EquipmentStatus::ACTIVE)
                ->count();

            $calibrationsDue = Equipment::where('tenant_id', $tenantId)
                ->where('status', EquipmentStatus::ACTIVE)
                ->whereNotNull('next_calibration_at')
                ->where('next_calibration_at', '<=', Carbon::now()->addDays(30))
                ->count();

            // ── Período anterior (para comparação) ──
            $daysDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $prevFrom = Carbon::parse($from)->subDays($daysDiff)->toDateString();
            $prevTo = Carbon::parse($from)->subDay()->toDateString();

            $prevOs = WorkOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$prevFrom, Carbon::parse($prevTo)->endOfDay()])
                ->count();

            $prevReceivable = AccountReceivable::where('tenant_id', $tenantId)
                ->whereIn('status', [
                    FinancialStatus::PENDING->value,
                    FinancialStatus::PARTIAL->value,
                    FinancialStatus::OVERDUE->value,
                ])
                ->whereBetween('due_date', [$prevFrom, $prevTo])
                ->sum(DB::raw('amount - amount_paid'));

            $prevReceived = floatval(bcadd(
                $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $prevFrom, $prevTo),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $prevFrom, $prevTo),
                2
            ));

            $prevQuotes = Quote::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$prevFrom, Carbon::parse($prevTo)->endOfDay()])
                ->count();

            $prevQuotesApproved = Quote::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$prevFrom, Carbon::parse($prevTo)->endOfDay()])
                ->where('status', QuoteStatus::APPROVED)
                ->count();

            $prevConversionRate = $prevQuotes > 0 ? round(($prevQuotesApproved / $prevQuotes) * 100, 1) : 0;

            $prevExpenses = Expense::where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [$prevFrom, $prevTo])
                ->where('status', ExpenseStatus::APPROVED)
                ->sum('amount');

            return ApiResponse::data([
                'period' => ['from' => $from, 'to' => $to],
                'operational' => [
                    'total_os' => $totalOs,
                    'os_completed' => $osCompleted,
                    'os_pending' => $osPending,
                    'os_cancelled' => $osCancelled,
                    'completion_rate' => $totalOs > 0 ? round(($osCompleted / $totalOs) * 100, 1) : 0,
                    'total_service_calls' => $totalServiceCalls,
                    'sc_completed' => $scCompleted,
                    'prev_total_os' => $prevOs,
                ],
                'financial' => [
                    'total_receivable' => floatval(bcadd($this->decimalString($totalReceivable), '0', 2)),
                    'total_received' => floatval(bcadd($this->decimalString($totalReceived), '0', 2)),
                    'total_overdue' => floatval(bcadd($this->decimalString($totalOverdue), '0', 2)),
                    'total_payable' => floatval(bcadd($this->decimalString($totalPayable), '0', 2)),
                    'total_paid' => floatval(bcadd($this->decimalString($totalPaid), '0', 2)),
                    'total_expenses' => floatval(bcadd($this->decimalString($totalExpenses), '0', 2)),
                    'net_balance' => floatval(bcsub(bcsub($this->decimalString($totalReceived), $this->decimalString($totalPaid), 2), $this->decimalString($totalExpenses), 2)),
                    'prev_total_receivable' => floatval(bcadd($this->decimalString($prevReceivable), '0', 2)),
                    'prev_total_received' => floatval(bcadd($this->decimalString($prevReceived), '0', 2)),
                    'prev_total_expenses' => floatval(bcadd($this->decimalString($prevExpenses), '0', 2)),
                ],
                'commercial' => [
                    'total_quotes' => $totalQuotes,
                    'approved_quotes' => $approvedQuotes,
                    'conversion_rate' => $conversionRate,
                    'quotes_value' => round((float) $quotesValue, 2),
                    'new_customers' => $newCustomers,
                    'total_active_customers' => $totalCustomers,
                    'prev_total_quotes' => $prevQuotes,
                    'prev_conversion_rate' => $prevConversionRate,
                ],
                'assets' => [
                    'total_equipments' => $totalEquipments,
                    'calibrations_due_30' => $calibrationsDue,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Analytics executiveSummary failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::data([
                'period' => ['from' => $from ?? now()->startOfMonth()->toDateString(), 'to' => $to ?? now()->endOfMonth()->toDateString()],
                'operational' => ['total_os' => 0, 'os_completed' => 0, 'os_pending' => 0, 'os_cancelled' => 0, 'completion_rate' => 0, 'total_service_calls' => 0, 'sc_completed' => 0, 'prev_total_os' => 0],
                'financial' => ['total_receivable' => 0, 'total_received' => 0, 'total_overdue' => 0, 'total_payable' => 0, 'total_paid' => 0, 'total_expenses' => 0, 'net_balance' => 0, 'prev_total_receivable' => 0, 'prev_total_received' => 0, 'prev_total_expenses' => 0],
                'commercial' => ['total_quotes' => 0, 'approved_quotes' => 0, 'conversion_rate' => 0, 'quotes_value' => 0, 'new_customers' => 0, 'total_active_customers' => 0, 'prev_total_quotes' => 0, 'prev_conversion_rate' => 0],
                'assets' => ['total_equipments' => 0, 'calibrations_due_30' => 0],
            ]);
        }
    }

    /**
     * Tendências mensais (últimos 12 meses) — séries temporais cross-module.
     */
    public function trends(AnalyticsTrendsRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId($request);
            $months = (int) $request->input('months', 12);
            $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();
            $monthCreatedExpr = $this->yearMonthExpression('created_at');
            $monthExpenseExpr = $this->yearMonthExpression('expense_date');

            $osData = WorkOrder::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->select(
                    DB::raw("{$monthCreatedExpr} as month"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = '".WorkOrder::STATUS_COMPLETED."' THEN 1 ELSE 0 END) as completed")
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $revenueData = $this->paymentTrendByMonth(AccountReceivable::class, $tenantId, $startDate);

            $expenseData = Expense::where('tenant_id', $tenantId)
                ->where('expense_date', '>=', $startDate)
                ->where('status', ExpenseStatus::APPROVED)
                ->select(
                    DB::raw("{$monthExpenseExpr} as month"),
                    DB::raw('COALESCE(SUM(amount), 0) as total')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $quoteData = Quote::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->select(
                    DB::raw("{$monthCreatedExpr} as month"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = '".QuoteStatus::APPROVED->value."' THEN 1 ELSE 0 END) as approved")
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $customerData = Customer::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->select(
                    DB::raw("{$monthCreatedExpr} as month"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $series = [];
            $current = $startDate->copy();
            while ($current->lte(Carbon::now()->startOfMonth())) {
                $key = $current->format('Y-m');
                $series[] = [
                    'month' => $current->format('M/y'),
                    'month_key' => $key,
                    'os_total' => (int) ($osData[$key]->total ?? 0),
                    'os_completed' => (int) ($osData[$key]->completed ?? 0),
                    'revenue' => round((float) ($revenueData[$key]->total ?? 0), 2),
                    'expenses' => round((float) ($expenseData[$key]->total ?? 0), 2),
                    'quotes_total' => (int) ($quoteData[$key]->total ?? 0),
                    'quotes_approved' => (int) ($quoteData[$key]->approved ?? 0),
                    'new_customers' => (int) ($customerData[$key]->total ?? 0),
                ];
                $current->addMonth();
            }

            return ApiResponse::data($series);
        } catch (\Throwable $e) {
            Log::error('Analytics trends failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::data([]);
        }
    }

    /**
     * Previsão futura baseada em regressão linear simples.
     */
    public function forecast(AnalyticsForecastRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId($request);
            $metric = $request->input('metric', 'revenue'); // revenue, expenses, os_total
            $months = (int) $request->input('months', 3);

            // Buscar histórico (últimos 12 meses)
            $historical = $this->getHistoricalData($metric, $tenantId);

            if (count($historical) < 3) {
                return ApiResponse::message('Dados insuficientes para previsão (mínimo 3 meses).', 422);
            }

            // Calcular Regressão Linear (y = mx + b)
            $n = count($historical);
            $xSum = 0;
            $ySum = 0;
            $xxSum = 0;
            $xySum = 0;

            // Mapear dados para x (0, 1, 2...) e y (valores)
            $values = array_values($historical);
            foreach ($values as $x => $y) {
                $xSum += $x;
                $ySum += $y;
                $xxSum += ($x * $x);
                $xySum += ($x * $y);
            }

            // Evitar divisão por zero
            $denominator = ($n * $xxSum) - ($xSum * $xSum);
            if ($denominator == 0) {
                return ApiResponse::message('Não foi possível calcular a tendência linear.', 422);
            }

            $slope = (($n * $xySum) - ($xSum * $ySum)) / $denominator;
            $intercept = ($ySum - ($slope * $xSum)) / $n;

            // Gerar previsões
            $forecast = [];
            $lastDate = Carbon::parse(array_key_last($historical));

            for ($i = 1; $i <= $months; $i++) {
                $nextX = ($n - 1) + $i;
                $predicted = ($slope * $nextX) + $intercept;
                $date = $lastDate->copy()->addMonths($i)->format('Y-m');

                $forecast[] = [
                    'month' => $lastDate->copy()->addMonths($i)->format('M/y'),
                    'value' => max(0, round($predicted, 2)), // Não permitir valores negativos
                    'type' => 'forecast',
                ];
            }

            return ApiResponse::data([
                'metric' => $metric,
                'historical' => array_map(fn ($k, $v) => [
                    'month' => Carbon::parse($k)->format('M/y'),
                    'value' => $v,
                    'type' => 'historical',
                ], array_keys($historical), $values),
                'forecast' => $forecast,
                'trend' => $slope > 0 ? 'up' : ($slope < 0 ? 'down' : 'neutral'),
                'accuracy' => $this->calculateForecastAccuracy($historical, $forecast),
            ]);

        } catch (\Throwable $e) {
            Log::error('Analytics forecast failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar previsão.', 500);
        }
    }

    /**
     * Detecção de anomalias (Z-Score > 2).
     */
    public function anomalies(AnalyticsForecastRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId($request);
            $metric = $request->input('metric', 'revenue');

            $data = $this->getHistoricalData($metric, $tenantId, 24); // Buscar 24 meses para melhor média

            if (count($data) < 6) {
                return ApiResponse::data(['anomalies' => []], 200, ['message' => 'Dados insuficientes.']);
            }

            // Calcular Média e Desvio Padrão
            $values = array_values($data);
            $mean = array_sum($values) / count($values);
            $variance = 0;
            foreach ($values as $v) {
                $variance += pow(($v - $mean), 2);
            }
            $stdDev = sqrt($variance / count($values));

            if ($stdDev == 0) {
                return ApiResponse::data(['anomalies' => []]);
            }

            $anomalies = [];
            foreach ($data as $date => $value) {
                $zScore = ($value - $mean) / $stdDev;

                if (abs($zScore) > 1.8) { // Threshold de sensibilidade (1.8 sigma)
                    $anomalies[] = [
                        'date' => Carbon::parse($date)->format('M/y'),
                        'value' => $value,
                        'z_score' => round($zScore, 2),
                        'severity' => abs($zScore) > 3 ? 'critical' : 'warning',
                        'type' => $zScore > 0 ? 'high' : 'low',
                        'message' => $zScore > 0
                            ? 'Valor acima do normal ('.round($zScore, 1).'x desvio)'
                            : 'Valor abaixo do normal ('.round($zScore, 1).'x desvio)',
                    ];
                }
            }

            return ApiResponse::data([
                'metric' => $metric,
                'anomalies' => array_reverse($anomalies), // Mais recentes primeiro
                'stats' => [
                    'mean' => round($mean, 2),
                    'std_dev' => round($stdDev, 2),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Analytics anomalies failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao detectar anomalias.', 500);
        }
    }

    /**
     * Motor de busca em linguagem natural (Simulado via Regex/Patterns).
     */
    public function nlQuery(AnalyticsNlQueryRequest $request): JsonResponse
    {
        try {
            $query = strtolower($request->input('query'));
            $tenantId = $this->tenantId($request);

            // 1. Interpretar Intenção
            $intent = 'unknown';
            $metric = null;
            $period = 'current_month';

            if (preg_match('/(vendas|receita|faturamento|ganhos)/', $query)) {
                $metric = 'revenue';
                $intent = 'kpi';
            } elseif (preg_match('/(despesas|gastos|custos|pagar)/', $query)) {
                $metric = 'expenses';
                $intent = 'kpi';
            } elseif (preg_match('/(lucro|resultado|saldo|liquido)/', $query)) {
                $metric = 'profit';
                $intent = 'kpi';
            } elseif (preg_match('/(os|chamados|serviços|ordens)/', $query)) {
                $metric = 'work_orders';
                $intent = 'kpi';
            } elseif (preg_match('/(clientes|novos)/', $query)) {
                $metric = 'new_customers';
                $intent = 'kpi';
            }

            // 2. Interpretar Período
            if (preg_match('/(passado|anterior|ultimo mes)/', $query)) {
                $period = 'last_month';
            } elseif (preg_match('/(ano|anual|este ano)/', $query)) {
                $period = 'this_year';
            } elseif (preg_match('/(hoje|dia)/', $query)) {
                $period = 'today';
            }

            if ($intent === 'unknown') {
                return ApiResponse::data([
                    'answer' => "Desculpe, não entendi sua pergunta. Tente perguntar sobre 'receita', 'despesas', 'lucro' ou 'OS'.",
                    'type' => 'text',
                ]);
            }

            // 3. Executar Consulta
            $val = 0;
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $label = 'este mês';

            if ($period === 'last_month') {
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                $label = 'mês passado';
            } elseif ($period === 'this_year') {
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                $label = 'este ano';
            } elseif ($period === 'today') {
                $startDate = Carbon::now()->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                $label = 'hoje';
            }

            switch ($metric) {
                case 'revenue':
                    $val = (float) bcadd(
                        $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $startDate, $endDate),
                        $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $startDate, $endDate),
                        2
                    );
                    $formatted = 'R$ '.number_format($val, 2, ',', '.');
                    $text = "A receita total {$label} foi de **{$formatted}**.";
                    break;

                case 'expenses':
                    $val = (float) Expense::where('tenant_id', $tenantId)
                        ->whereBetween('expense_date', [$startDate, $endDate])
                        ->where('status', ExpenseStatus::APPROVED)
                        ->sum('amount');
                    $formatted = 'R$ '.number_format($val, 2, ',', '.');
                    $text = "As despesas aprovadas {$label} totalizaram **{$formatted}**.";
                    break;

                case 'profit':
                    $rev = (float) bcadd(
                        $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $startDate, $endDate),
                        $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $startDate, $endDate),
                        2
                    );
                    $exp = (float) Expense::where('tenant_id', $tenantId)
                        ->whereBetween('expense_date', [$startDate, $endDate])->where('status', ExpenseStatus::APPROVED)->sum('amount');
                    $val = $rev - $exp;
                    $formatted = 'R$ '.number_format($val, 2, ',', '.');
                    $text = "O lucro líquido estimativo {$label} foi de **{$formatted}**.";
                    break;

                case 'work_orders':
                    $val = WorkOrder::where('tenant_id', $tenantId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->count();
                    $text = "Foram criadas **{$val}** Ordens de Serviço {$label}.";
                    break;

                case 'new_customers':
                    $val = Customer::where('tenant_id', $tenantId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->count();
                    $text = "Conquistamos **{$val}** novos clientes {$label}.";
                    break;
            }

            return ApiResponse::data([
                'answer' => $text,
                'query_analysis' => ['metric' => $metric, 'period' => $period, 'intent' => $intent],
                'value' => $val,
                'type' => 'kpi_result',
            ]);

        } catch (\Throwable $e) {
            Log::error('Analytics nlQuery failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao processar pergunta.', 500);
        }
    }

    /**
     * Helper para buscar dados históricos genéricos.
     *
     * @return array<string, float>
     */
    private function getHistoricalData(string $metric, ?int $tenantId, int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();
        $data = [];

        if ($metric === 'revenue') {
            foreach ($this->paymentTrendByMonth(AccountReceivable::class, $tenantId, $startDate) as $month => $row) {
                $data["{$month}-01"] = (float) ($row->total ?? 0);
            }
        } elseif ($metric === 'expenses') {
            $rows = Expense::where('tenant_id', $tenantId)
                ->where('expense_date', '>=', $startDate)
                ->where('status', ExpenseStatus::APPROVED)
                ->get(['expense_date', 'amount']);

            foreach ($rows as $row) {
                if (! $row->expense_date) {
                    continue;
                }

                $month = $row->expense_date->copy()->startOfMonth()->format('Y-m-01');
                $amount = $this->decimalString($row->amount ?? 0);
                $data[$month] = bcadd($this->decimalString($data[$month] ?? 0), $amount, 2);
            }
        } elseif ($metric === 'os_total') {
            $rows = WorkOrder::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->get(['created_at']);

            foreach ($rows as $row) {
                if (! $row->created_at) {
                    continue;
                }

                $month = $row->created_at->copy()->startOfMonth()->format('Y-m-01');
                $data[$month] = ($data[$month] ?? 0) + 1;
            }
        }

        // Preencher buracos com 0
        $filled = [];
        $current = $startDate->copy();
        while ($current->lte(Carbon::now()->startOfMonth())) {
            $key = $current->format('Y-m-01');
            $filled[$key] = isset($data[$key]) ? (float) $data[$key] : 0;
            $current->addMonth();
        }

        return $filled;
    }

    /**
     * @param  array<string, float>  $historical
     * @param  array<int, array<string, mixed>>  $forecast
     */
    private function calculateForecastAccuracy(array $historical, array $forecast): string
    {
        if (count($historical) < 3 || count($forecast) === 0) {
            return 'low';
        }
        $values = array_values($historical);
        $mean = array_sum($values) / count($values);
        if ($mean == 0) {
            return 'low';
        }
        $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / count($values);
        $cv = sqrt($variance) / abs($mean);
        if ($cv < 0.15) {
            return 'high';
        }
        if ($cv < 0.35) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @return numeric-string
     */
    private function sumPaymentsForPeriod(string $payableType, ?int $tenantId, Carbon|string $from, Carbon|string $to): string
    {
        $fromDate = $from instanceof Carbon ? $from->toDateString() : $from;
        $toDate = $to instanceof Carbon ? $to->toDateString() : $to;

        return $this->decimalString(Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', $payableType)
            ->whereBetween('payment_date', [$fromDate, $toDate.' 23:59:59'])
            ->sum('amount'));
    }

    /**
     * @return numeric-string
     */
    private function sumLegacyPaidAmountWithoutPayments(AccountReceivable|AccountPayable $model, ?int $tenantId, Carbon|string $from, Carbon|string $to): string
    {
        $fromDate = $from instanceof Carbon ? $from->toDateString() : $from;
        $toDate = $to instanceof Carbon ? $to->toDateString() : $to;

        return $this->decimalString($model::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereDoesntHave('payments')
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$fromDate, $toDate.' 23:59:59'])
            ->sum('amount_paid'));
    }

    /**
     * @return Collection<string, mixed>
     */
    private function paymentTrendByMonth(string $payableType, ?int $tenantId, Carbon $startDate): Collection
    {
        $monthExpr = $this->yearMonthExpression('payment_date');
        $paymentRows = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', $payableType)
            ->where('payment_date', '>=', $startDate->toDateString())
            ->selectRaw("{$monthExpr} as month, COALESCE(SUM(amount), 0) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $legacyMonthExpr = $this->yearMonthExpression('COALESCE(paid_at, due_date)');
        $legacyRows = ($payableType === AccountReceivable::class ? new AccountReceivable : new AccountPayable)::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereDoesntHave('payments')
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$startDate->toDateString(), now()->endOfMonth()->toDateString().' 23:59:59'])
            ->selectRaw("{$legacyMonthExpr} as month, COALESCE(SUM(amount_paid), 0) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($legacyRows as $row) {
            $month = (string) $row->getAttribute('month');
            $rowTotal = $this->decimalString($row->getAttribute('total'));
            if ($paymentRows->has($month)) {
                $paymentRow = $paymentRows[$month];
                $paymentRow->setAttribute('total', bcadd($this->decimalString($paymentRow->getAttribute('total')), $rowTotal, 2));
                continue;
            }

            $paymentRow = new Payment;
            $paymentRow->setAttribute('month', $month);
            $paymentRow->setAttribute('total', $rowTotal);
            $paymentRows[$month] = $paymentRow;
        }

        return $paymentRows->sortKeys();
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    private function yearMonthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * @return numeric-string
     */
    private function decimalString(float|int|string|null $value): string
    {
        $decimal = number_format((float) $value, 2, '.', '');

        return $decimal;
    }
}
