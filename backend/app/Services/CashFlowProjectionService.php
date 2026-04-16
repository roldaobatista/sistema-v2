<?php

namespace App\Services;

use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Projeção de Fluxo de Caixa — Entradas e Saídas previstas vs realizadas.
 */
class CashFlowProjectionService
{
    /**
     * Gera projeção de fluxo de caixa para um período.
     *
     * @return array{
     *   period: array{from: string, to: string},
     *   summary: array,
     *   daily: array,
     *   by_week: array
     * }
     */
    public function project(Carbon $from, Carbon $to, int $tenantId): array
    {
        // ── Entradas ──
        $entradasPrevistas = (string) AccountReceivable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, $to])
            ->whereIn('status', [
                AccountReceivable::STATUS_PENDING,
                AccountReceivable::STATUS_PARTIAL,
                AccountReceivable::STATUS_OVERDUE,
            ])
            ->sum(DB::raw('amount - COALESCE(amount_paid, 0)'));

        $entradasRealizadas = bcadd(
            $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $from, $to),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $from, $to),
            2
        );

        // ── Saídas ──
        $saidasPrevistas = (string) AccountPayable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, $to])
            ->whereNotIn('status', [
                FinancialStatus::PAID->value,
                FinancialStatus::CANCELLED->value,
                FinancialStatus::RENEGOTIATED->value,
            ])
            ->sum(DB::raw('amount - COALESCE(amount_paid, 0)'));

        $saidasRealizadas = bcadd(
            $this->sumPaymentsForPeriod(AccountPayable::class, $tenantId, $from, $to),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $from, $to),
            2
        );

        // ── Saldo ──
        $totalEntradas = bcadd($entradasPrevistas, $entradasRealizadas, 2);
        $totalSaidas = bcadd($saidasPrevistas, $saidasRealizadas, 2);
        $saldoPrevisto = bcsub($totalEntradas, $totalSaidas, 2);

        // ── Breakdown por semana ──
        $byWeek = $this->generateWeekly($from, $to, $tenantId);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'entradas_previstas' => bcadd($entradasPrevistas, '0', 2),
                'entradas_realizadas' => bcadd($entradasRealizadas, '0', 2),
                'total_entradas' => $totalEntradas,
                'saidas_previstas' => bcadd($saidasPrevistas, '0', 2),
                'saidas_realizadas' => bcadd($saidasRealizadas, '0', 2),
                'total_saidas' => $totalSaidas,
                'saldo_previsto' => $saldoPrevisto,
            ],
            'by_week' => $byWeek,
        ];
    }

    private function generateWeekly(Carbon $from, Carbon $to, int $tenantId): array
    {
        $weeks = [];
        $cursor = $from->copy()->startOfWeek();
        $weekNum = 1;

        while ($cursor <= $to) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek();
            if ($weekEnd > $to) {
                $weekEnd = $to->copy();
            }
            if ($weekStart < $from) {
                $weekStart = $from->copy();
            }

            $entradas = (string) AccountReceivable::where('tenant_id', $tenantId)
                ->whereBetween('due_date', [$weekStart, $weekEnd])
                ->whereIn('status', [
                    AccountReceivable::STATUS_PENDING,
                    AccountReceivable::STATUS_PARTIAL,
                    AccountReceivable::STATUS_OVERDUE,
                ])
                ->sum(DB::raw('amount - COALESCE(amount_paid, 0)'));

            $entradasRealizadas = bcadd(
                $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $weekStart, $weekEnd),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $weekStart, $weekEnd),
                2
            );

            $saidas = (string) AccountPayable::where('tenant_id', $tenantId)
                ->whereBetween('due_date', [$weekStart, $weekEnd])
                ->whereNotIn('status', [
                    FinancialStatus::PAID->value,
                    FinancialStatus::CANCELLED->value,
                    FinancialStatus::RENEGOTIATED->value,
                ])
                ->sum(DB::raw('amount - COALESCE(amount_paid, 0)'));

            $saidasRealizadas = bcadd(
                $this->sumPaymentsForPeriod(AccountPayable::class, $tenantId, $weekStart, $weekEnd),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $weekStart, $weekEnd),
                2
            );

            $totalEntradasSemana = bcadd($entradas, $entradasRealizadas, 2);
            $totalSaidasSemana = bcadd($saidas, $saidasRealizadas, 2);

            $weeks[] = [
                'week' => $weekNum,
                'from' => $weekStart->toDateString(),
                'to' => $weekEnd->toDateString(),
                'entradas_previstas' => bcadd($entradas, '0', 2),
                'entradas_realizadas' => bcadd($entradasRealizadas, '0', 2),
                'saidas_previstas' => bcadd($saidas, '0', 2),
                'saidas_realizadas' => bcadd($saidasRealizadas, '0', 2),
                'saldo' => bcsub($totalEntradasSemana, $totalSaidasSemana, 2),
            ];

            $cursor->addWeek();
            $weekNum++;
        }

        return $weeks;
    }

    private function sumPaymentsForPeriod(string $payableType, int $tenantId, Carbon $from, Carbon $to): string
    {
        return (string) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', $payableType)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    private function sumLegacyPaidAmountWithoutPayments(AccountReceivable|AccountPayable $model, int $tenantId, Carbon $from, Carbon $to): string
    {
        return (string) $model::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereDoesntHave('payments')
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from->toDateString(), $to->toDateString()])
            ->sum('amount_paid');
    }
}
