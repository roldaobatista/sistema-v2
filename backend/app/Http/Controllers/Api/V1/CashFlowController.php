<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\CashFlowIndexRequest;
use App\Http\Requests\Financial\DreReportRequest;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Expense;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashFlowController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    private function periodExpression(string $column): string
    {
        $allowed = [
            'paid_at', 'due_date', 'created_at', 'updated_at', 'completed_at', 'payment_date', 'expense_date',
            'accounts_receivable.due_date', 'accounts_payable.due_date', 'expenses.expense_date',
            'COALESCE(paid_at, due_date)',
            'COALESCE(accounts_receivable.paid_at, accounts_receivable.due_date)',
            'COALESCE(accounts_payable.paid_at, accounts_payable.due_date)',
        ];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid column for periodExpression: {$column}");
        }

        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function osNumberFilter(Request $request): ?string
    {
        $osNumber = trim((string) $request->get('os_number', ''));

        return $osNumber !== '' ? SearchSanitizer::escapeLike($osNumber) : null;
    }

    private function applyReceivableOsFilter(Builder $query, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        $query->whereHas('workOrder', function (Builder $woQuery) use ($osNumber) {
            $woQuery->where(function (Builder $whereQuery) use ($osNumber) {
                $whereQuery->where('os_number', 'like', "%{$osNumber}%")
                    ->orWhere('number', 'like', "%{$osNumber}%");
            });
        });
    }

    private function applyExpenseOsFilter(Builder $query, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        $query->whereHas('workOrder', function (Builder $woQuery) use ($osNumber) {
            $woQuery->where(function (Builder $whereQuery) use ($osNumber) {
                $whereQuery->where('os_number', 'like', "%{$osNumber}%")
                    ->orWhere('number', 'like', "%{$osNumber}%");
            });
        });
    }

    private function applyPayableIdentifierFilter(Builder $query, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        $query->where(function (Builder $whereQuery) use ($osNumber) {
            $whereQuery->where('description', 'like', "%{$osNumber}%")
                ->orWhere('notes', 'like', "%{$osNumber}%");
        });
    }

    private function applyPaymentOsFilter(Builder $query, string $payableType, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        if ($payableType === AccountReceivable::class) {
            $query->whereHasMorph('payable', [AccountReceivable::class], function (Builder $payableQuery) use ($osNumber) {
                $this->applyReceivableOsFilter($payableQuery, $osNumber);
            });

            return;
        }

        $query->whereHasMorph('payable', [AccountPayable::class], function (Builder $payableQuery) use ($osNumber) {
            $this->applyPayableIdentifierFilter($payableQuery, $osNumber);
        });
    }

    private function legacyPaidAmountWithoutPayments(
        int $tenantId,
        string $payableType,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $osNumber
    ): float {
        if ($payableType === AccountReceivable::class) {
            $query = AccountReceivable::query()
                ->where('tenant_id', $tenantId)
                ->where('amount_paid', '>', 0)
                ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from, $to])
                ->whereDoesntHave('payments');
            $this->applyReceivableOsFilter($query, $osNumber);

            return (float) $query->sum('amount_paid');
        }

        $query = AccountPayable::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from, $to])
            ->whereDoesntHave('payments');
        $this->applyPayableIdentifierFilter($query, $osNumber);

        return (float) $query->sum('amount_paid');
    }

    private function paidAmountInPeriod(
        int $tenantId,
        string $payableType,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $osNumber
    ): float {
        $fromDateTime = $from->copy()->startOfDay();
        $toDateTime = $to->copy()->endOfDay();

        $paymentsQuery = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', $payableType)
            ->whereBetween('payment_date', [$fromDateTime, $toDateTime]);
        $this->applyPaymentOsFilter($paymentsQuery, $payableType, $osNumber);

        $paymentsAmount = (float) $paymentsQuery->sum('amount');
        $legacyAmount = $this->legacyPaidAmountWithoutPayments($tenantId, $payableType, $fromDateTime, $toDateTime, $osNumber);

        return $paymentsAmount + $legacyAmount;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function resolveCashFlowWindow(CashFlowIndexRequest $request): array
    {
        $months = (int) ($request->integer('months') ?: 12);
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $start = ($dateFrom ?? now()->subMonths($months - 1))->copy()->startOfMonth();
        $end = ($dateTo ?? $start->copy()->addMonths($months - 1))->copy()->endOfMonth();

        if ($start->greaterThan($end)) {
            abort(ApiResponse::message('Data inicial não pode ser posterior a data final', 422));
        }

        $resolvedMonths = max(
            1,
            (($end->year - $start->year) * 12) + ($end->month - $start->month) + 1
        );

        return [$start, $end, $resolvedMonths];
    }

    /**
     * @param  literal-string  $table
     * @param  literal-string  $dateColumn
     * @param  literal-string  $amountColumn
     * @param  literal-string|null  $statusColumn
     * @return array<int|string, mixed>
     */
    private function monthlyTotalsFromTable(
        string $table,
        string $dateColumn,
        string $amountColumn,
        int $tenantId,
        Carbon $start,
        Carbon $end,
        ?string $osNumber,
        ?string $statusColumn = null,
        ?string $statusValue = null
    ): array {
        /** @var literal-string $qualifiedDateColumn */
        $qualifiedDateColumn = "{$table}.{$dateColumn}";
        $periodExpr = $this->periodExpression($qualifiedDateColumn);

        $query = DB::table($table)
            ->selectRaw("{$periodExpr} as month, SUM({$table}.{$amountColumn}) as total")
            ->where("{$table}.tenant_id", $tenantId)
            ->whereBetween($qualifiedDateColumn, [$start->copy()->startOfDay()->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString()]);

        if ($statusColumn !== null && $statusValue !== null) {
            $query->where("{$table}.{$statusColumn}", $statusValue);
        }

        if ($table === 'accounts_receivable' && $osNumber) {
            $query->join('work_orders as receivable_work_orders', function ($join) {
                $join->on('receivable_work_orders.id', '=', 'accounts_receivable.work_order_id')
                    ->on('receivable_work_orders.tenant_id', '=', 'accounts_receivable.tenant_id');
            })->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('receivable_work_orders.os_number', 'like', "%{$osNumber}%")
                    ->orWhere('receivable_work_orders.number', 'like', "%{$osNumber}%");
            });
        }

        if ($table === 'expenses' && $osNumber) {
            $query->join('work_orders as expense_work_orders', function ($join) {
                $join->on('expense_work_orders.id', '=', 'expenses.work_order_id')
                    ->on('expense_work_orders.tenant_id', '=', 'expenses.tenant_id');
            })->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('expense_work_orders.os_number', 'like', "%{$osNumber}%")
                    ->orWhere('expense_work_orders.number', 'like', "%{$osNumber}%");
            });
        }

        if ($table === 'accounts_payable' && $osNumber) {
            $query->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('accounts_payable.description', 'like', "%{$osNumber}%")
                    ->orWhere('accounts_payable.notes', 'like', "%{$osNumber}%");
            });
        }

        return $query
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->month => (float) $row->total])
            ->all();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function monthlyTotalsWithoutOs(int $tenantId, Carbon $start, Carbon $end): array
    {
        $startDate = $start->copy()->startOfDay()->toDateTimeString();
        $endDate = $end->copy()->endOfDay()->toDateTimeString();

        $receivablesPeriodExpr = $this->periodExpression('due_date');
        $payablesPeriodExpr = $this->periodExpression('due_date');
        $expensesPeriodExpr = $this->periodExpression('expense_date');

        $union = DB::table('accounts_receivable')
            ->selectRaw("'receivables' as source, {$receivablesPeriodExpr} as month, SUM(amount) as total")
            ->where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$startDate, $endDate])
            ->groupBy('month')
            ->unionAll(
                DB::table('accounts_payable')
                    ->selectRaw("'payables' as source, {$payablesPeriodExpr} as month, SUM(amount) as total")
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('due_date', [$startDate, $endDate])
                    ->groupBy('month')
            )
            ->unionAll(
                DB::table('expenses')
                    ->selectRaw("'expenses' as source, {$expensesPeriodExpr} as month, SUM(amount) as total")
                    ->where('tenant_id', $tenantId)
                    ->where('status', ExpenseStatus::APPROVED->value)
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->groupBy('month')
            );

        $rows = DB::query()
            ->fromSub($union, 'monthly_cash_flow_totals')
            ->select('source', 'month', 'total')
            ->orderBy('month')
            ->get();

        $totals = [
            'receivables' => [],
            'payables' => [],
            'expenses' => [],
        ];

        foreach ($rows as $row) {
            $totals[$row->source][(string) $row->month] = (float) $row->total;
        }

        return $totals;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function monthlyPaidTotalsFromTables(int $tenantId, Carbon $start, Carbon $end, ?string $osNumber): array
    {
        $startDateTime = $start->copy()->startOfDay()->toDateTimeString();
        $endDateTime = $end->copy()->endOfDay()->toDateTimeString();
        $paymentPeriodExpr = $this->periodExpression('payment_date');
        $legacyReceivableExpr = $this->periodExpression('COALESCE(accounts_receivable.paid_at, accounts_receivable.due_date)');
        $legacyPayableExpr = $this->periodExpression('COALESCE(accounts_payable.paid_at, accounts_payable.due_date)');

        if ($osNumber) {
            return [
                'receivables' => $this->monthlyPaidTotalsForReceivablesWithOs($tenantId, $startDateTime, $endDateTime, $osNumber, $paymentPeriodExpr, $legacyReceivableExpr),
                'payables' => $this->monthlyPaidTotalsForPayablesWithOs($tenantId, $startDateTime, $endDateTime, $osNumber, $paymentPeriodExpr, $legacyPayableExpr),
            ];
        }

        $paidUnion = DB::table('payments')
            ->selectRaw("'receivables' as source, {$paymentPeriodExpr} as month, SUM(amount) as total")
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountReceivable::class)
            ->whereBetween('payment_date', [$startDateTime, $endDateTime])
            ->groupBy('month')
            ->unionAll(
                DB::table('payments')
                    ->selectRaw("'payables' as source, {$paymentPeriodExpr} as month, SUM(amount) as total")
                    ->where('tenant_id', $tenantId)
                    ->where('payable_type', AccountPayable::class)
                    ->whereBetween('payment_date', [$startDateTime, $endDateTime])
                    ->groupBy('month')
            )
            ->unionAll(
                DB::table('accounts_receivable')
                    ->leftJoin('payments as linked_receivable_payments', function ($join) {
                        $join->on('linked_receivable_payments.payable_id', '=', 'accounts_receivable.id')
                            ->where('linked_receivable_payments.payable_type', '=', AccountReceivable::class);
                    })
                    ->selectRaw("'receivables' as source, {$legacyReceivableExpr} as month, SUM(accounts_receivable.amount_paid) as total")
                    ->where('accounts_receivable.tenant_id', $tenantId)
                    ->where('accounts_receivable.amount_paid', '>', 0)
                    ->whereNull('linked_receivable_payments.id')
                    ->whereRaw('COALESCE(accounts_receivable.paid_at, accounts_receivable.due_date) BETWEEN ? AND ?', [$startDateTime, $endDateTime])
                    ->groupBy('month')
            )
            ->unionAll(
                DB::table('accounts_payable')
                    ->leftJoin('payments as linked_payable_payments', function ($join) {
                        $join->on('linked_payable_payments.payable_id', '=', 'accounts_payable.id')
                            ->where('linked_payable_payments.payable_type', '=', AccountPayable::class);
                    })
                    ->selectRaw("'payables' as source, {$legacyPayableExpr} as month, SUM(accounts_payable.amount_paid) as total")
                    ->where('accounts_payable.tenant_id', $tenantId)
                    ->where('accounts_payable.amount_paid', '>', 0)
                    ->whereNull('linked_payable_payments.id')
                    ->whereRaw('COALESCE(accounts_payable.paid_at, accounts_payable.due_date) BETWEEN ? AND ?', [$startDateTime, $endDateTime])
                    ->groupBy('month')
            );

        $rows = DB::query()
            ->fromSub($paidUnion, 'monthly_cash_flow_paid_totals')
            ->selectRaw('source, month, SUM(total) as total')
            ->groupBy('source', 'month')
            ->orderBy('month')
            ->get();

        $receivables = [];
        $payables = [];

        foreach ($rows as $row) {
            $key = (string) $row->month;
            if ($row->source === 'receivables') {
                $receivables[$key] = (float) $row->total;
                continue;
            }

            $payables[$key] = (float) $row->total;
        }

        return [
            'receivables' => $receivables,
            'payables' => $payables,
        ];
    }

    /**
     * @param  literal-string  $paymentPeriodExpr
     * @param  literal-string  $legacyPeriodExpr
     * @return array<int|string, mixed>
     */
    private function monthlyPaidTotalsForReceivablesWithOs(
        int $tenantId,
        string $startDateTime,
        string $endDateTime,
        string $osNumber,
        string $paymentPeriodExpr,
        string $legacyPeriodExpr
    ): array {
        $payments = DB::table('payments')
            ->join('accounts_receivable', function ($join) {
                $join->on('accounts_receivable.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', '=', AccountReceivable::class);
            })
            ->join('work_orders as receivable_work_orders', function ($join) {
                $join->on('receivable_work_orders.id', '=', 'accounts_receivable.work_order_id')
                    ->on('receivable_work_orders.tenant_id', '=', 'accounts_receivable.tenant_id');
            })
            ->selectRaw("{$paymentPeriodExpr} as month, SUM(payments.amount) as total")
            ->where('payments.tenant_id', $tenantId)
            ->whereBetween('payments.payment_date', [$startDateTime, $endDateTime])
            ->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('receivable_work_orders.os_number', 'like', "%{$osNumber}%")
                    ->orWhere('receivable_work_orders.number', 'like', "%{$osNumber}%");
            })
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->month => (float) $row->total])
            ->all();

        $legacy = DB::table('accounts_receivable')
            ->leftJoin('payments as linked_receivable_payments', function ($join) {
                $join->on('linked_receivable_payments.payable_id', '=', 'accounts_receivable.id')
                    ->where('linked_receivable_payments.payable_type', '=', AccountReceivable::class);
            })
            ->join('work_orders as receivable_work_orders', function ($join) {
                $join->on('receivable_work_orders.id', '=', 'accounts_receivable.work_order_id')
                    ->on('receivable_work_orders.tenant_id', '=', 'accounts_receivable.tenant_id');
            })
            ->selectRaw("{$legacyPeriodExpr} as month, SUM(accounts_receivable.amount_paid) as total")
            ->where('accounts_receivable.tenant_id', $tenantId)
            ->where('accounts_receivable.amount_paid', '>', 0)
            ->whereNull('linked_receivable_payments.id')
            ->whereRaw('COALESCE(accounts_receivable.paid_at, accounts_receivable.due_date) BETWEEN ? AND ?', [$startDateTime, $endDateTime])
            ->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('receivable_work_orders.os_number', 'like', "%{$osNumber}%")
                    ->orWhere('receivable_work_orders.number', 'like', "%{$osNumber}%");
            })
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($legacy as $row) {
            $key = (string) $row->month;
            $payments[$key] = (float) ($payments[$key] ?? 0) + (float) $row->total;
        }

        return $payments;
    }

    /**
     * @param  literal-string  $paymentPeriodExpr
     * @param  literal-string  $legacyPeriodExpr
     * @return array<int|string, mixed>
     */
    private function monthlyPaidTotalsForPayablesWithOs(
        int $tenantId,
        string $startDateTime,
        string $endDateTime,
        string $osNumber,
        string $paymentPeriodExpr,
        string $legacyPeriodExpr
    ): array {
        $payments = DB::table('payments')
            ->join('accounts_payable', function ($join) {
                $join->on('accounts_payable.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', '=', AccountPayable::class);
            })
            ->selectRaw("{$paymentPeriodExpr} as month, SUM(payments.amount) as total")
            ->where('payments.tenant_id', $tenantId)
            ->whereBetween('payments.payment_date', [$startDateTime, $endDateTime])
            ->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('accounts_payable.description', 'like', "%{$osNumber}%")
                    ->orWhere('accounts_payable.notes', 'like', "%{$osNumber}%");
            })
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->month => (float) $row->total])
            ->all();

        $legacy = DB::table('accounts_payable')
            ->leftJoin('payments as linked_payable_payments', function ($join) {
                $join->on('linked_payable_payments.payable_id', '=', 'accounts_payable.id')
                    ->where('linked_payable_payments.payable_type', '=', AccountPayable::class);
            })
            ->selectRaw("{$legacyPeriodExpr} as month, SUM(accounts_payable.amount_paid) as total")
            ->where('accounts_payable.tenant_id', $tenantId)
            ->where('accounts_payable.amount_paid', '>', 0)
            ->whereNull('linked_payable_payments.id')
            ->whereRaw('COALESCE(accounts_payable.paid_at, accounts_payable.due_date) BETWEEN ? AND ?', [$startDateTime, $endDateTime])
            ->where(function ($whereQuery) use ($osNumber) {
                $whereQuery->where('accounts_payable.description', 'like', "%{$osNumber}%")
                    ->orWhere('accounts_payable.notes', 'like', "%{$osNumber}%");
            })
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($legacy as $row) {
            $key = (string) $row->month;
            $payments[$key] = (float) ($payments[$key] ?? 0) + (float) $row->total;
        }

        return $payments;
    }

    public function cashFlow(CashFlowIndexRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $osNumber = $this->osNumberFilter($request);
            [$start, $end, $months] = $this->resolveCashFlowWindow($request);

            if ($osNumber) {
                $receivables = $this->monthlyTotalsFromTable('accounts_receivable', 'due_date', 'amount', $tenantId, $start, $end, $osNumber);
                $payables = $this->monthlyTotalsFromTable('accounts_payable', 'due_date', 'amount', $tenantId, $start, $end, $osNumber);
                $expenses = $this->monthlyTotalsFromTable('expenses', 'expense_date', 'amount', $tenantId, $start, $end, $osNumber, 'status', ExpenseStatus::APPROVED->value);
            } else {
                $baseTotals = $this->monthlyTotalsWithoutOs($tenantId, $start, $end);
                $receivables = $baseTotals['receivables'];
                $payables = $baseTotals['payables'];
                $expenses = $baseTotals['expenses'];
            }

            $allPaid = $this->monthlyPaidTotalsFromTables($tenantId, $start, $end, $osNumber);
            $receivablesPaidByMonth = $allPaid['receivables'];
            $payablesPaidByMonth = $allPaid['payables'];

            $data = [];
            $current = $start->copy();
            for ($i = 0; $i < $months; $i++) {
                $key = $current->format('Y-m');
                $receivablesTotal = (float) ($receivables[$key] ?? 0);
                $payablesTotal = (float) ($payables[$key] ?? 0);
                $expensesTotal = (float) ($expenses[$key] ?? 0);
                $receivablesPaid = (float) ($receivablesPaidByMonth[$key] ?? 0);
                $payablesPaid = (float) ($payablesPaidByMonth[$key] ?? 0);

                $data[] = [
                    'month' => $key,
                    'label' => $current->translatedFormat('M/Y'),
                    'receivables_total' => $receivablesTotal,
                    'receivables_paid' => $receivablesPaid,
                    'payables_total' => $payablesTotal,
                    'payables_paid' => $payablesPaid,
                    'expenses_total' => $expensesTotal,
                    'balance' => $receivablesTotal - ($payablesTotal + $expensesTotal),
                    'cash_balance' => $receivablesPaid - ($payablesPaid + $expensesTotal),
                ];
                $current->addMonth();
            }

            // Return data both at root level (for numeric indexing) and under 'data' key (for assertJsonCount)
            $response = $data;
            $response['data'] = $data;

            return ApiResponse::data($data, 200, $response);
        } catch (\Throwable $e) {
            Log::error('CashFlow failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message('Erro ao gerar fluxo de caixa: '.$e->getMessage(), 500);
        }
    }

    public function dre(DreReportRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $osNumber = $this->osNumberFilter($request);
            $from = $request->date('date_from') ?? now()->startOfMonth();
            $to = $request->date('date_to') ?? now()->endOfDay();

            if ($from->greaterThan($to)) {
                return ApiResponse::message('Data inicial não pode ser posterior a data final', 422);
            }

            $revenue = $this->paidAmountInPeriod($tenantId, AccountReceivable::class, $from, $to, $osNumber);
            $costs = $this->paidAmountInPeriod($tenantId, AccountPayable::class, $from, $to, $osNumber);

            $expensesQuery = Expense::query()
                ->where('tenant_id', $tenantId)
                ->where('status', ExpenseStatus::APPROVED->value)
                ->whereBetween('expense_date', [$from, $to]);
            $this->applyExpenseOsFilter($expensesQuery, $osNumber);
            $expenses = (float) $expensesQuery->sum('amount');

            $totalCosts = $costs + $expenses;

            $receivablesPendingQuery = AccountReceivable::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value]);
            $this->applyReceivableOsFilter($receivablesPendingQuery, $osNumber);
            $receivablesPending = (float) $receivablesPendingQuery->sum(DB::raw('amount - amount_paid'));

            $payablesPendingQuery = AccountPayable::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value]);
            $this->applyPayableIdentifierFilter($payablesPendingQuery, $osNumber);
            $payablesPending = (float) $payablesPendingQuery->sum(DB::raw('amount - amount_paid'));

            return ApiResponse::data([
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'os_number' => $osNumber],
                'revenue' => $revenue,
                'costs' => $costs,
                'expenses' => $expenses,
                'total_costs' => $totalCosts,
                'gross_profit' => $revenue - $totalCosts,
                'receivables_pending' => $receivablesPending,
                'payables_pending' => $payablesPending,
                'net_balance' => $revenue - $totalCosts + $receivablesPending - $payablesPending,
            ]);
        } catch (\Throwable $e) {
            Log::error('DRE failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar DRE', 500);
        }
    }

    public function dreComparativo(DreReportRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $osNumber = $this->osNumberFilter($request);
            $from = $request->date('date_from') ?? now()->startOfMonth();
            $to = $request->date('date_to') ?? now()->endOfDay();

            if ($from->greaterThan($to)) {
                return ApiResponse::message('Data inicial não pode ser posterior a data final', 422);
            }

            $days = $from->diffInDays($to);
            $prevTo = $from->copy()->subDay();
            $prevFrom = $prevTo->copy()->subDays($days);

            $current = $this->calcDrePeriod($tenantId, $from, $to, $osNumber);
            $previous = $this->calcDrePeriod($tenantId, $prevFrom, $prevTo, $osNumber);

            $variation = fn ($cur, $prev) => $prev > 0 ? round((($cur - $prev) / $prev) * 100, 1) : ($cur > 0 ? 100 : 0);

            return ApiResponse::data([
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'os_number' => $osNumber],
                'current' => $current,
                'previous' => $previous,
                'variation' => [
                    'revenue' => $variation($current['revenue'], $previous['revenue']),
                    'total_costs' => $variation($current['total_costs'], $previous['total_costs']),
                    'gross_profit' => $variation($current['gross_profit'], $previous['gross_profit']),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DRE Comparativo failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar DRE comparativo', 500);
        }
    }

    private function calcDrePeriod(
        int $tenantId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $osNumber = null
    ): array {
        $revenue = $this->paidAmountInPeriod($tenantId, AccountReceivable::class, $from, $to, $osNumber);
        $costs = $this->paidAmountInPeriod($tenantId, AccountPayable::class, $from, $to, $osNumber);

        $expensesQuery = Expense::query()
            ->where('tenant_id', $tenantId)
            ->where('status', ExpenseStatus::APPROVED->value)
            ->whereBetween('expense_date', [$from, $to]);
        $this->applyExpenseOsFilter($expensesQuery, $osNumber);
        $expenses = (float) $expensesQuery->sum('amount');

        $totalCosts = $costs + $expenses;

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'revenue' => $revenue,
            'costs' => $costs,
            'expenses' => $expenses,
            'total_costs' => $totalCosts,
            'gross_profit' => $revenue - $totalCosts,
        ];
    }
}
