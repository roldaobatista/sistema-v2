<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demonstração de Resultado do Exercício (DRE).
 * Calcula Receitas, Custos, Despesas e Lucro Líquido.
 */
class DREService
{
    /**
     * Gera o DRE consolidado para um período.
     *
     * @return array{
     *   period: array{from: string, to: string},
     *   receitas_brutas: string,
     *   deducoes: string,
     *   receitas_liquidas: string,
     *   custos_servicos: string,
     *   lucro_bruto: string,
     *   despesas_operacionais: string,
     *   despesas_administrativas: string,
     *   despesas_financeiras: string,
     *   resultado_operacional: string,
     *   resultado_liquido: string,
     *   by_month: array
     * }
     */
    public function generate(Carbon $from, Carbon $to, int $tenantId): array
    {
        // Receitas brutas: total de CR (pagas + parciais)
        $receitasBrutas = bcadd(
            $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $from, $to),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $from, $to),
            2
        );

        // Receitas brutas por todas emitidas
        $receitasEmitidas = (string) AccountReceivable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, $to])
            ->sum('amount');

        // Deduções (devoluções, cancelamentos)
        $deducoes = '0.00';

        // Custos dos serviços: despesas vinculadas a OS (peças, deslocamento, etc)
        $custosServicos = (string) Expense::where('tenant_id', $tenantId)
            ->whereNotNull('work_order_id')
            ->where('affects_net_value', true)
            ->whereBetween('expense_date', [$from, $to])
            ->whereNot('status', 'rejected')
            ->sum('amount');

        // Despesas operacionais: despesas sem OS (administrativas)
        $despesasOperacionais = (string) Expense::where('tenant_id', $tenantId)
            ->whereNull('work_order_id')
            ->whereBetween('expense_date', [$from, $to])
            ->whereNot('status', 'rejected')
            ->sum('amount');

        // Despesas financeiras: CP pagas (fornecedores, etc)
        $despesasFinanceiras = bcadd(
            $this->sumPaymentsForPeriod(AccountPayable::class, $tenantId, $from, $to),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $from, $to),
            2
        );

        // Cálculos
        $receitasLiquidas = bcsub($receitasBrutas, $deducoes, 2);
        $lucroBruto = bcsub($receitasLiquidas, $custosServicos, 2);
        $totalDespesas = bcadd($despesasOperacionais, $despesasFinanceiras, 2);
        $resultadoOperacional = bcsub($lucroBruto, $despesasOperacionais, 2);
        $resultadoLiquido = bcsub($lucroBruto, $totalDespesas, 2);

        // Por mês
        $byMonth = $this->generateMonthly($from, $to, $tenantId);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'receitas_emitidas' => bcadd($receitasEmitidas, '0', 2),
            'receitas_brutas' => bcadd($receitasBrutas, '0', 2),
            'deducoes' => bcadd($deducoes, '0', 2),
            'receitas_liquidas' => bcadd($receitasLiquidas, '0', 2),
            'custos_servicos' => bcadd($custosServicos, '0', 2),
            'lucro_bruto' => bcadd($lucroBruto, '0', 2),
            'despesas_operacionais' => bcadd($despesasOperacionais, '0', 2),
            'despesas_financeiras' => bcadd($despesasFinanceiras, '0', 2),
            'resultado_operacional' => bcadd($resultadoOperacional, '0', 2),
            'resultado_liquido' => bcadd($resultadoLiquido, '0', 2),
            'margem_bruta_percent' => bccomp($receitasBrutas, '0', 2) > 0
                ? bcdiv(bcmul($lucroBruto, '100', 4), $receitasBrutas, 2)
                : '0.00',
            'margem_liquida_percent' => bccomp($receitasBrutas, '0', 2) > 0
                ? bcdiv(bcmul($resultadoLiquido, '100', 4), $receitasBrutas, 2)
                : '0.00',
            'by_month' => $byMonth,
        ];
    }

    private function generateMonthly(Carbon $from, Carbon $to, int $tenantId): array
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();

        while ($cursor <= $to) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd > $to) {
                $monthEnd = $to->copy();
            }

            $receitas = bcadd(
                $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $monthStart, $monthEnd),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $monthStart, $monthEnd),
                2
            );

            $custos = (string) Expense::where('tenant_id', $tenantId)
                ->whereNotNull('work_order_id')
                ->where('affects_net_value', true)
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->whereNot('status', 'rejected')
                ->sum('amount');

            $despesas = (string) Expense::where('tenant_id', $tenantId)
                ->whereNull('work_order_id')
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->whereNot('status', 'rejected')
                ->sum('amount');

            $months[] = [
                'month' => $cursor->format('Y-m'),
                'receitas' => bcadd($receitas, '0', 2),
                'custos' => bcadd($custos, '0', 2),
                'despesas' => bcadd($despesas, '0', 2),
                'resultado' => bcsub(bcsub($receitas, $custos, 2), $despesas, 2),
            ];

            $cursor->addMonth();
        }

        return $months;
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
