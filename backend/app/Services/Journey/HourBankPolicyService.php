<?php

namespace App\Services\Journey;

use App\Models\HourBankTransaction;
use App\Models\JourneyRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HourBankPolicyService
{
    public function resolvePolicy(User $user): JourneyRule
    {
        $tenantId = $user->current_tenant_id;

        $policy = JourneyRule::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if ($policy) {
            return $policy;
        }

        return $this->createDefaultPolicy($tenantId);
    }

    public function getBalance(User $user): int
    {
        $lastTransaction = HourBankTransaction::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return $lastTransaction ? (int) round($lastTransaction->balance_after * 60) : 0;
    }

    public function addEntry(
        User $user,
        int $minutes,
        string $type,
        Carbon $referenceDate,
        ?int $journeyEntryId = null,
        ?string $notes = null,
    ): HourBankTransaction {
        $policy = $this->resolvePolicy($user);
        $currentBalance = $this->getBalance($user);
        $hoursDecimal = round($minutes / 60, 2);
        $newBalance = round(($currentBalance + $minutes) / 60, 2);

        // Check balance limits
        $newBalanceMinutes = $currentBalance + $minutes;
        if ($policy->isBalanceExceeded($newBalanceMinutes)) {
            Log::warning('JourneyRule: balance exceeded', [
                'user_id' => $user->id,
                'current_minutes' => $currentBalance,
                'delta_minutes' => $minutes,
                'policy_id' => $policy->id,
            ]);

            if ($policy->block_on_negative_exceeded && $newBalanceMinutes < 0) {
                $hoursDecimal = round(-$currentBalance / 60, 2);
                $newBalance = 0;
            }
        }

        return HourBankTransaction::withoutGlobalScope('tenant')->create([
            'tenant_id' => $user->current_tenant_id,
            'user_id' => $user->id,
            'journey_entry_id' => $journeyEntryId,
            'type' => $type,
            'hours' => $hoursDecimal,
            'balance_before' => round($currentBalance / 60, 2),
            'balance_after' => $newBalance,
            'reference_date' => $referenceDate->format('Y-m-d'),
            'notes' => $notes,
        ]);
    }

    public function processExpiredEntries(int $tenantId): int
    {
        $policy = JourneyRule::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $policy) {
            return 0;
        }

        $cutoffDate = now()->subDays($policy->compensation_period_days);
        $expiredCount = 0;

        $users = HourBankTransaction::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('reference_date', '<=', $cutoffDate)
            ->whereNotIn('type', ['expiry', 'payout'])
            ->whereNull('expired_at')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($users as $userId) {
            /** @var User|null $user */
            $user = User::withoutGlobalScope('tenant')->find($userId);
            if (! $user) {
                continue;
            }

            $expiredTransactions = HourBankTransaction::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('reference_date', '<=', $cutoffDate)
                ->whereNotIn('type', ['expiry', 'payout'])
                ->whereNull('expired_at')
                ->where('hours', '>', 0)
                ->get();

            foreach ($expiredTransactions as $transaction) {
                $transaction->update(['expired_at' => now()]);

                $this->addEntry(
                    $user,
                    -(int) round($transaction->hours * 60),
                    'expiry',
                    now(),
                    $transaction->journey_entry_id,
                    "Expiração automática: saldo de {$transaction->reference_date->format('d/m/Y')}",
                );

                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getMonthlySnapshot(User $user, string $yearMonth): array
    {
        [$year, $month] = explode('-', $yearMonth);
        $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $transactions = HourBankTransaction::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->whereBetween('reference_date', [$startDate, $endDate])
            ->orderBy('reference_date')
            ->get();

        $credits = $transactions->where('hours', '>', 0)->sum('hours');
        $debits = $transactions->where('hours', '<', 0)->sum('hours');
        $currentBalance = $this->getBalance($user);

        return [
            'user_id' => $user->id,
            'year_month' => $yearMonth,
            'credits_hours' => round($credits, 2),
            'debits_hours' => round(abs($debits), 2),
            'net_hours' => round($credits + $debits, 2),
            'current_balance_hours' => round($currentBalance / 60, 2),
            'transactions_count' => $transactions->count(),
        ];
    }

    private function createDefaultPolicy(int $tenantId): JourneyRule
    {
        return JourneyRule::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'name' => 'CLT Padrão',
            // Legado
            'daily_hours' => 8.00,
            'weekly_hours' => 44.00,
            'overtime_weekday_pct' => 50,
            'overtime_weekend_pct' => 100,
            'overtime_holiday_pct' => 100,
            'night_shift_pct' => 20,
            'night_start' => '22:00',
            'night_end' => '05:00',
            'uses_hour_bank' => true,
            'hour_bank_expiry_months' => 6,
            'is_default' => true,
            // Motor Operacional
            'regime_type' => 'clt_mensal',
            'daily_hours_limit' => 480,
            'weekly_hours_limit' => 2640,
            'break_minutes' => 60,
            'is_active' => true,
            // Banco de horas
            'compensation_period_days' => 30,
            'max_positive_balance_minutes' => 6000,
            'max_negative_balance_minutes' => 2400,
            'block_on_negative_exceeded' => true,
            'auto_compensate' => false,
            'convert_expired_to_payment' => false,
            'overtime_50_multiplier' => 1.50,
            'overtime_100_multiplier' => 2.00,
            'requires_two_level_approval' => true,
        ]);
    }
}
