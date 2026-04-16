<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;

class ChurnCalculationService
{
    /**
     * Calculates and updates health score for a customer (4.31).
     * Health Index: 0 (churned) to 100 (perfectly engaged)
     */
    public function calculateScore(int $tenantId, int $customerId): array
    {
        $healthIndex = 100;
        $factors = [];

        // Factor 1: Days since last completed work order
        $lastOs = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->max('completed_at');

        $daysSinceLastOs = $lastOs ? now()->diffInDays($lastOs) : 999;

        if ($daysSinceLastOs > 365) {
            $healthIndex -= 40;
            $factors[] = 'Sem OS concluída há mais de 1 ano';
        } elseif ($daysSinceLastOs > 180) {
            $healthIndex -= 25;
            $factors[] = "Sem OS há {$daysSinceLastOs} dias";
        } elseif ($daysSinceLastOs > 90) {
            $healthIndex -= 10;
            $factors[] = "Última OS há {$daysSinceLastOs} dias";
        }

        // Factor 2: Overdue payments
        $overdueCount = DB::table('accounts_receivable')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', 'overdue')
            ->whereNull('deleted_at')
            ->count();

        if ($overdueCount > 3) {
            $healthIndex -= 25;
            $factors[] = "{$overdueCount} faturas em atraso";
        } elseif ($overdueCount > 0) {
            $healthIndex -= 10;
            $factors[] = "{$overdueCount} fatura(s) em atraso";
        }

        // Factor 3: NPS / satisfaction
        $avgNps = DB::table('satisfaction_surveys')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->avg('nps_score');

        if ($avgNps !== null && $avgNps < 6) {
            $healthIndex -= 20;
            $factors[] = "NPS médio baixo: {$avgNps}";
        }

        // Factor 4: Contract status
        $hasActiveContract = DB::table('recurring_contracts')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveContract) {
            $healthIndex += 15;
            $factors[] = 'Contrato recorrente ativo (+15)';
        }

        $healthIndex = max(0, min(100, $healthIndex));
        $riskLevel = match (true) {
            $healthIndex <= 30 => 'high',
            $healthIndex <= 60 => 'medium',
            default => 'low',
        };

        // Persist
        DB::table('customer_health_scores')->updateOrInsert(
            ['tenant_id' => $tenantId, 'customer_id' => $customerId],
            [
                'health_index' => $healthIndex,
                'risk_level' => $riskLevel,
                'factors' => json_encode($factors),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return [
            'customer_id' => $customerId,
            'health_index' => $healthIndex,
            'risk_level' => $riskLevel,
            'factors' => $factors,
        ];
    }
}
