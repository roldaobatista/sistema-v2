<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditRiskAnalysisService
{
    /**
     * Analyzes a customer's payment history and returns a risk score (0-100).
     * Used for predictive default detection on quotes (3.23).
     *
     * @param  bool  $persist  If true, caches the risk result on the customer model
     */
    public function analyzeCustomer(int $tenantId, int $customerId, bool $persist = false): array
    {
        // 1. Payment history metrics
        $payments = DB::table('accounts_receivable')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count"),
                DB::raw("SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count"),
                DB::raw('AVG(CASE WHEN paid_at IS NOT NULL AND due_date IS NOT NULL THEN DATEDIFF(paid_at, due_date) ELSE NULL END) as avg_days_late'),
                DB::raw("SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue_amount"),
            ])
            ->first();

        $totalInvoices = $payments->total_invoices ?? 0;
        $overdueCount = $payments->overdue_count ?? 0;
        $avgDaysLate = $payments->avg_days_late ?? 0;
        $totalOverdue = $payments->total_overdue_amount ?? 0;

        // 2. Calculate risk factors
        $overdueRate = $totalInvoices > 0 ? ($overdueCount / $totalInvoices) * 100 : 0;

        // 3. Credit Score (0 = no risk, 100 = maximum risk)
        $riskScore = 0;
        $factors = [];

        // Factor: Overdue rate (max 40 points)
        $overdueRisk = min($overdueRate * 0.8, 40);
        $riskScore += $overdueRisk;
        if ($overdueRisk > 10) {
            $factors[] = "Taxa de inadimplência: {$overdueRate}%";
        }

        // Factor: Average days late (max 30 points)
        $lateRisk = min(max($avgDaysLate, 0) * 1.5, 30);
        $riskScore += $lateRisk;
        if ($avgDaysLate > 5) {
            $factors[] = 'Atraso médio: '.round($avgDaysLate).' dias';
        }

        // Factor: Outstanding overdue amount (max 20 points)
        $amountRisk = min($totalOverdue / 1000, 20);
        $riskScore += $amountRisk;
        if ($totalOverdue > 500) {
            $factors[] = 'Valor em atraso: R$ '.number_format($totalOverdue, 2, ',', '.');
        }

        // Factor: No payment history (10 points)
        if ($totalInvoices === 0) {
            $riskScore += 10;
            $factors[] = 'Sem histórico de pagamentos';
        }

        $riskLevel = match (true) {
            $riskScore >= 70 => 'critical',
            $riskScore >= 40 => 'high',
            $riskScore >= 20 => 'medium',
            default => 'low',
        };

        $result = [
            'customer_id' => $customerId,
            'risk_score' => round(min($riskScore, 100), 1),
            'risk_level' => $riskLevel,
            'factors' => $factors,
            'metrics' => [
                'total_invoices' => $totalInvoices,
                'overdue_count' => $overdueCount,
                'overdue_rate' => round($overdueRate, 1),
                'avg_days_late' => round($avgDaysLate, 1),
                'total_overdue_amount' => $totalOverdue,
            ],
        ];

        // Persist risk score to customer record when requested
        if ($persist) {
            try {
                DB::table('customers')
                    ->where('id', $customerId)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'credit_risk_score' => $result['risk_score'],
                        'credit_risk_level' => $result['risk_level'],
                        'credit_risk_analyzed_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                Log::warning("Falha ao persistir risk score para customer #{$customerId}: {$e->getMessage()}");
            }
        }

        return $result;
    }
}
