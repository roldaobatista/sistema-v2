<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FinancialStatus;
use App\Events\PaymentReceived;
use App\Http\Controllers\Api\V1\Financial\FinancialAdvancedController as FinancialAdvanced;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateInstallmentsRequest;
use App\Http\Requests\Finance\ImportCnabRequest;
use App\Http\Requests\Finance\PartialPaymentReceivableRequest;
use App\Http\Requests\Finance\SimulateInstallmentsRequest;
use App\Http\Requests\Finance\StoreCollectionRuleRequest;
use App\Http\Requests\Finance\UpdateCollectionRuleRequest;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CollectionRule;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FinanceAdvancedController extends Controller
{
    use ResolvesCurrentTenant;
    // ─── #9B Importação CNAB 240/400 ────────────────────────────
    // Nota: o matching por nosso_numero/numero_documento requer campos extras
    // na tabela accounts_receivable. Atualmente tenta correspondência por notes/description.

    public function importCnab(ImportCnabRequest $request): JsonResponse
    {
        $this->authorize('create', AccountReceivable::class);
        $tenantId = $this->tenantId();
        $file = $request->file('file');
        $layout = $request->input('layout');
        $type = $request->input('type');
        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $results = ['processed' => 0, 'matched' => 0, 'errors' => []];

        if ($type === 'retorno') {
            foreach ($lines as $index => $line) {
                if ($layout === 'cnab240' && strlen($line) !== 240) {
                    continue;
                }
                if ($layout === 'cnab400' && strlen($line) !== 400) {
                    continue;
                }

                try {
                    $parsed = $layout === 'cnab240'
                        ? $this->parseCnab240Line($line)
                        : $this->parseCnab400Line($line);

                    if (! $parsed) {
                        continue;
                    }
                    $results['processed']++;

                    // 1) Matching preciso: campos dedicados (nosso_numero / numero_documento)
                    $receivable = AccountReceivable::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($parsed) {
                            $q->where('nosso_numero', $parsed['nosso_numero'])
                                ->orWhere('numero_documento', $parsed['documento']);
                        })
                        ->first();

                    // 2) Fallback: matching por notes (compatibilidade retroativa)
                    if (! $receivable && ! empty($parsed['nosso_numero'])) {
                        $receivable = AccountReceivable::where('tenant_id', $tenantId)
                            ->where(function ($q) use ($parsed) {
                                $q->where('notes', 'like', '%'.$parsed['nosso_numero'].'%')
                                    ->orWhere('notes', 'like', '%'.$parsed['documento'].'%');
                            })
                            ->first();
                    }

                    if ($receivable) {
                        DB::transaction(function () use ($receivable, $parsed) {
                            $locked = AccountReceivable::lockForUpdate()->findOrFail($receivable->id);
                            $newAmountPaid = bcadd((string) ($locked->amount_paid ?? 0), (string) ($parsed['valor_pago'] ?? 0), 2);
                            $locked->update([
                                'paid_at' => $parsed['data_pagamento'],
                                'amount_paid' => $newAmountPaid,
                            ]);
                            $locked->recalculateStatus();
                        });
                        $results['matched']++;
                    } else {
                        $results['unmatched'][] = [
                            'nosso_numero' => $parsed['nosso_numero'] ?? null,
                            'documento' => $parsed['documento'] ?? null,
                            'valor_pago' => $parsed['valor_pago'] ?? null,
                            'line' => $index,
                        ];
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = "Line {$index}: {$e->getMessage()}";
                }
            }
        }

        return ApiResponse::data($results, 200, ['message' => "CNAB {$layout} importado."]);
    }

    // ─── #10 Fluxo de Caixa Projetado ───────────────────────────

    public function cashFlowProjection(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $months = $request->input('months', 6);
        $startDate = Carbon::now();

        $projection = [];

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $label = $monthStart->format('Y-m');

            $receivables = (string) AccountReceivable::where('tenant_id', $tenantId)
                ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum(DB::raw('amount - amount_paid'));

            $payables = (string) AccountPayable::where('tenant_id', $tenantId)
                ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum(DB::raw('amount - amount_paid'));

            $projection[] = [
                'month' => $label,
                'receivables' => $receivables,
                'payables' => $payables,
                'net_projection' => bcsub($receivables, $payables, 2),
            ];
        }

        // Saldo do mês atual (recebidos - pagos)
        $receivedThisMonth = (string) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountReceivable::class)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $paidThisMonth = (string) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountPayable::class)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $currentBalance = bcsub($receivedThisMonth, $paidThisMonth, 2);

        return ApiResponse::data([
            'current_balance' => $currentBalance,
            'projection' => $projection,
        ]);
    }

    // ─── #12B Pagamento Parcial de Conta ────────────────────────

    public function partialPayment(PartialPaymentReceivableRequest $request, AccountReceivable $receivable): JsonResponse
    {
        if ($error = $this->ensureTenantOwnership($receivable, 'Título')) {
            return $error;
        }

        try {
            $payment = DB::transaction(function () use ($request, $receivable) {
                $locked = AccountReceivable::lockForUpdate()->find($receivable->id);

                // Check cancelled/renegotiated INSIDE lock to prevent TOCTOU
                $lockedStatus = $locked->status instanceof FinancialStatus ? $locked->status : FinancialStatus::tryFrom((string) $locked->status);
                if (in_array($lockedStatus, [FinancialStatus::CANCELLED, FinancialStatus::RENEGOTIATED], true)) {
                    abort(422, 'Título cancelado/renegociado não pode receber baixa');
                }

                $remaining = bcsub((string) $locked->amount, (string) $locked->amount_paid, 2);
                if (bccomp($remaining, '0', 2) <= 0) {
                    abort(422, 'Título já liquidado');
                }

                $amount = (string) $request->input('amount');
                if (bccomp($amount, $remaining, 2) > 0) {
                    abort(422, 'Valor excede o saldo devedor (R$ '.number_format((float) $remaining, 2, ',', '.').')');
                }

                // Cria Payment record — o booted() do Payment model
                // atualiza amount_paid e recalculateStatus() automaticamente
                return Payment::create([
                    'tenant_id' => $this->tenantId(),
                    'payable_type' => AccountReceivable::class,
                    'payable_id' => $locked->id,
                    'received_by' => $request->user()->id,
                    'amount' => $amount,
                    'payment_method' => $request->input('payment_method'),
                    'payment_date' => $request->input('payment_date', now()->toDateString()),
                    'notes' => $request->input('notes'),
                ]);
            });

            PaymentReceived::dispatch($receivable->fresh(), $payment);

            $fresh = $receivable->fresh();
            $newRemaining = bcsub((string) $fresh->amount, (string) $fresh->amount_paid, 2);

            return ApiResponse::data([
                'payment_id' => $payment->id,
                'remaining' => $newRemaining,
                'status' => $fresh->status,
            ], 201, ['message' => "Pagamento parcial de R$ {$payment->amount} registrado."]);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Error processing partial payment: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro ao processar pagamento.', 500);
        }
    }

    // ─── #13 DRE por Centro de Custo ────────────────────────────

    public function dreByCostCenter(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $revenue = DB::table('payments')
            ->join('accounts_receivable', function ($join) {
                $join->on('payments.payable_id', '=', 'accounts_receivable.id')
                    ->where('payments.payable_type', '=', AccountReceivable::class);
            })
            ->where('payments.tenant_id', $tenantId)
            ->whereBetween('payments.payment_date', [$from, $to])
            ->whereNull('accounts_receivable.deleted_at')
            ->whereNotNull('accounts_receivable.chart_of_account_id')
            ->selectRaw('accounts_receivable.chart_of_account_id, SUM(payments.amount) as total')
            ->groupBy('chart_of_account_id')
            ->get()
            ->keyBy('chart_of_account_id');

        $expenses = DB::table('payments')
            ->join('accounts_payable', function ($join) {
                $join->on('payments.payable_id', '=', 'accounts_payable.id')
                    ->where('payments.payable_type', '=', AccountPayable::class);
            })
            ->where('payments.tenant_id', $tenantId)
            ->whereBetween('payments.payment_date', [$from, $to])
            ->whereNull('accounts_payable.deleted_at')
            ->whereNotNull('accounts_payable.chart_of_account_id')
            ->selectRaw('accounts_payable.chart_of_account_id, SUM(payments.amount) as total')
            ->groupBy('chart_of_account_id')
            ->get()
            ->keyBy('chart_of_account_id');

        $accountIds = $revenue->keys()->merge($expenses->keys())->unique();
        $accountsMap = DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $accountIds)
            ->pluck('name', 'id');

        $dre = $accountIds->map(function ($accountId) use ($revenue, $expenses, $accountsMap) {
            $rev = (string) ($revenue[$accountId]->total ?? 0);
            $exp = (string) ($expenses[$accountId]->total ?? 0);
            $profit = bcsub($rev, $exp, 2);
            $margin = bccomp($rev, '0', 2) > 0
                ? (float) bcmul(bcdiv($profit, $rev, 4), '100', 1)
                : 0;

            return [
                'chart_of_account_id' => $accountId,
                'account_name' => $accountsMap[$accountId] ?? 'Sem Conta Contábil',
                'revenue' => $rev,
                'expenses' => $exp,
                'profit' => $profit,
                'margin' => $margin,
            ];
        })->values();

        $totalRevenue = (string) $dre->sum('revenue');
        $totalExpenses = (string) $dre->sum('expenses');
        $totalProfit = bcsub($totalRevenue, $totalExpenses, 2);
        $totals = [
            'revenue' => $totalRevenue,
            'expenses' => $totalExpenses,
            'profit' => $totalProfit,
        ];
        $totals['margin'] = bccomp($totalRevenue, '0', 2) > 0
            ? (float) bcmul(bcdiv($totalProfit, $totalRevenue, 4), '100', 1) : 0;

        return ApiResponse::data(['dre' => $dre, 'totals' => $totals]);
    }

    // ─── Collection rules (delegate to Financial\FinancialAdvancedController) ───

    public function collectionRules(Request $request): JsonResponse
    {
        return (new FinancialAdvanced)->collectionRules($request);
    }

    public function storeCollectionRule(StoreCollectionRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $rule = CollectionRule::create([
            ...$validated,
            'tenant_id' => $this->tenantId(),
        ]);

        return ApiResponse::data($rule, 201);
    }

    public function updateCollectionRule(UpdateCollectionRuleRequest $request, $rule): JsonResponse
    {
        $collectionRule = CollectionRule::where('tenant_id', $this->tenantId())
            ->findOrFail($rule);

        $validated = $request->validated();

        $collectionRule->update($validated);

        return ApiResponse::data($collectionRule);
    }

    public function deleteCollectionRule(Request $request, $rule): JsonResponse
    {
        $collectionRule = CollectionRule::where('tenant_id', $this->tenantId())
            ->findOrFail($rule);

        $collectionRule->delete();

        return ApiResponse::message('Regra de cobrança removida.');
    }

    /** Alias para rota installment/simulate. */
    public function simulateInstallment(SimulateInstallmentsRequest $request): JsonResponse
    {
        return $this->simulateInstallments($request);
    }

    /** Alias para rota installment/create. */
    public function createInstallment(CreateInstallmentsRequest $request): JsonResponse
    {
        return $this->createInstallments($request);
    }

    // ─── #14 Parcelamento Inteligente ───────────────────────────

    public function simulateInstallments(SimulateInstallmentsRequest $request): JsonResponse
    {
        $total = $request->input('total_amount');
        $n = $request->input('installments');
        $rate = $request->input('interest_rate', 0) / 100;
        $firstDue = Carbon::parse($request->input('first_due_date'));

        $installments = [];
        if ($rate > 0) {
            // PMT uses float pow() since bcmath has no exponentiation — acceptable for simulation
            $factor = pow(1 + $rate, $n);
            $pmt = $total * ($rate * $factor) / ($factor - 1);
            $balance = (string) $total;

            for ($i = 1; $i <= $n; $i++) {
                $interest = bcmul($balance, (string) $rate, 4);
                $pmtStr = bcadd((string) $pmt, '0', 2);
                $principal = bcsub($pmtStr, $interest, 2);
                $balance = bcsub($balance, $principal, 2);

                $installments[] = [
                    'number' => $i,
                    'due_date' => $firstDue->copy()->addMonths($i - 1)->toDateString(),
                    'amount' => (float) bcadd((string) $pmt, '0', 2),
                    'principal' => (float) $principal,
                    'interest' => (float) $interest,
                    'balance' => (float) max(0, (float) $balance),
                ];
            }
        } else {
            $totalStr = (string) $total;
            $nStr = (string) $n;
            // floor(total * 100 / n) / 100 via bcmath
            $base = bcdiv(bcmul($totalStr, '100', 0), $nStr, 0);
            $base = bcdiv($base, '100', 2);
            $remainder = bcsub($totalStr, bcmul($base, $nStr, 2), 2);

            for ($i = 1; $i <= $n; $i++) {
                $amt = $i === $n ? bcadd($base, $remainder, 2) : $base;
                $paid = bcadd(bcmul($base, (string) $i, 2), ($i === $n ? $remainder : '0'), 2);
                $installments[] = [
                    'number' => $i,
                    'due_date' => $firstDue->copy()->addMonths($i - 1)->toDateString(),
                    'amount' => (float) $amt,
                    'principal' => (float) $amt,
                    'interest' => 0,
                    'balance' => (float) bcsub($totalStr, $paid, 2),
                ];
            }
        }

        return ApiResponse::data([
            'total_amount' => (float) bcadd((string) $total, '0', 2),
            'total_with_interest' => (float) bcadd((string) collect($installments)->sum('amount'), '0', 2),
            'interest_rate' => $rate * 100,
            'installments' => $installments,
        ]);
    }

    public function createInstallments(CreateInstallmentsRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $created = [];
        $total = count($request->input('installments'));

        DB::beginTransaction();

        try {
            foreach ($request->input('installments') as $i => $inst) {
                $ar = AccountReceivable::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $request->input('customer_id'),
                    'description' => $request->input('description').' ('.($i + 1)."/{$total})",
                    'amount' => $inst['amount'],
                    'amount_paid' => 0,
                    'due_date' => $inst['due_date'],
                    'status' => FinancialStatus::PENDING->value,
                    'created_by' => $request->user()->id,
                ]);
                $created[] = $ar->id;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating installments: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro ao criar parcelas.', 500);
        }

        return ApiResponse::data(['ids' => $created], 201, ['message' => count($created).' parcela(s) criada(s).']);
    }

    // ─── #15 Dashboard de Inadimplência ─────────────────────────

    public function delinquencyDashboard(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $overdue = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
            ->where('due_date', '<', now());

        $total = (clone $overdue)->sum(DB::raw('amount - amount_paid'));
        $count = (clone $overdue)->count();

        // Aging buckets
        $buckets = [
            '1-30' => (clone $overdue)->where('due_date', '>=', now()->subDays(30))->sum(DB::raw('amount - amount_paid')),
            '31-60' => (clone $overdue)->whereBetween('due_date', [now()->subDays(60), now()->subDays(31)])->sum(DB::raw('amount - amount_paid')),
            '61-90' => (clone $overdue)->whereBetween('due_date', [now()->subDays(90), now()->subDays(61)])->sum(DB::raw('amount - amount_paid')),
            '90+' => (clone $overdue)->where('due_date', '<', now()->subDays(90))->sum(DB::raw('amount - amount_paid')),
        ];

        // Top clientes inadimplentes
        $topCustomers = AccountReceivable::where('accounts_receivable.tenant_id', $tenantId)
            ->whereNotIn('accounts_receivable.status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
            ->where('accounts_receivable.due_date', '<', now())
            ->join('customers', function ($join) use ($tenantId) {
                $join->on('accounts_receivable.customer_id', '=', 'customers.id')
                    ->where('customers.tenant_id', $tenantId);
            })
            ->selectRaw('customers.id, customers.name, SUM(accounts_receivable.amount - accounts_receivable.amount_paid) as total_due, COUNT(*) as count')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_due')
            ->limit(10)
            ->get();

        // Tendência (últimos 6 meses)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $trend[] = [
                'month' => $monthStart->format('Y-m'),
                'total' => (float) bcadd((string) AccountReceivable::where('tenant_id', $tenantId)
                    ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
                    ->where('due_date', '<', $monthEnd)
                    ->where('created_at', '<=', $monthEnd)
                    ->sum(DB::raw('amount - amount_paid')), '0', 2),
            ];
        }

        $totalReceivables = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
            ->sum(DB::raw('amount - amount_paid'));
        $rate = $totalReceivables > 0 ? round(($total / $totalReceivables) * 100, 1) : 0;

        return ApiResponse::data([
            'total_overdue' => (float) bcadd((string) $total, '0', 2),
            'overdue_count' => $count,
            'delinquency_rate' => $rate,
            'aging_buckets' => $buckets,
            'top_customers' => $topCustomers,
            'trend' => $trend,
        ]);
    }

    // ─── CNAB Parsers ───────────────────────────────────────────

    private function parseCnab240Line(string $line): ?array
    {
        $segmento = substr($line, 13, 1);
        if ($segmento !== 'T' && $segmento !== 'U') {
            return null;
        }

        if ($segmento === 'T') {
            return [
                'nosso_numero' => trim(substr($line, 37, 20)),
                'documento' => trim(substr($line, 58, 15)),
                'valor' => bcdiv(trim(substr($line, 81, 15)), '100', 2),
                'status' => $this->parseCnab240Status(substr($line, 15, 2)),
            ];
        }

        return [
            'data_pagamento' => $this->parseCnabDate(substr($line, 137, 8)),
            'valor_pago' => bcdiv(trim(substr($line, 77, 15)), '100', 2),
            'juros' => bcdiv(trim(substr($line, 17, 15)), '100', 2),
            'desconto' => bcdiv(trim(substr($line, 32, 15)), '100', 2),
            'status' => 'paid',
        ];
    }

    private function parseCnab400Line(string $line): ?array
    {
        $tipo = substr($line, 0, 1);
        if ($tipo !== '1') {
            return null;
        }

        return [
            'nosso_numero' => trim(substr($line, 62, 10)),
            'documento' => trim(substr($line, 116, 10)),
            'data_pagamento' => $this->parseCnabDate(substr($line, 295, 6)),
            'valor_pago' => bcdiv(trim(substr($line, 253, 13)), '100', 2),
            'juros' => bcdiv(trim(substr($line, 266, 13)), '100', 2),
            'desconto' => bcdiv(trim(substr($line, 240, 13)), '100', 2),
            'status' => 'paid',
        ];
    }

    private function parseCnab240Status(string $code): string
    {
        return match ($code) {
            '06', '17' => 'paid',
            '02' => 'registered',
            '09' => 'rejected',
            default => 'unknown',
        };
    }

    private function parseCnabDate(string $raw): ?string
    {
        if (strlen($raw) === 8) {
            return Carbon::createFromFormat('dmY', $raw)?->toDateString();
        }
        if (strlen($raw) === 6) {
            return Carbon::createFromFormat('dmy', $raw)?->toDateString();
        }

        return null;
    }
}
