<?php

namespace App\Services;

use App\Models\HourBankTransaction;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HourBankExpiryService
{
    /**
     * Process hour bank expiry for a specific user.
     *
     * Art. 59 §5 CLT: Acordo individual → 6 meses máximo.
     * Art. 59 §2 CLT: Acordo coletivo → 12 meses máximo.
     * Art. 59 §6 CLT: Compensação mensal → zera todo mês.
     *
     * Positive expired balance converts to paid overtime at 50%.
     * Negative balance (employee owes hours) does NOT expire — company absorbs.
     */
    public function processExpiry(int $userId, int $tenantId): array
    {
        $rule = JourneyRule::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if (! $rule || ! $rule->uses_hour_bank) {
            return ['processed' => false, 'reason' => 'Hour bank not enabled'];
        }

        $cutoffDate = $this->getExpiryDate($rule);

        // Check if current balance is negative — company absorbs, never expires
        $currentBalance = $this->getCurrentBalance($userId);
        if ($currentBalance < 0) {
            return [
                'processed' => true,
                'expired_hours' => 0,
                'reason' => 'Negative balance does not expire',
            ];
        }

        $expiringBalance = $this->getExpiringBalance($userId, $tenantId, $cutoffDate);

        if ($expiringBalance <= 0) {
            return [
                'processed' => true,
                'expired_hours' => 0,
                'reason' => 'No balance to expire',
            ];
        }

        return DB::transaction(function () use ($userId, $tenantId, $expiringBalance, $cutoffDate) {
            $currentBalance = $this->getCurrentBalance($userId);

            $transaction = HourBankTransaction::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'expiry',
                'hours' => -$expiringBalance,
                'balance_before' => $currentBalance,
                'balance_after' => bcsub((string) $currentBalance, (string) $expiringBalance, 2),
                'reference_date' => now()->toDateString(),
                'expired_at' => now(),
                'notes' => "Expiração automática: {$expiringBalance}h anteriores a {$cutoffDate->toDateString()} convertidas em HE 50%",
            ]);

            Log::info('Hour bank expiry processed', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'expired_hours' => $expiringBalance,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);

            return [
                'processed' => true,
                'expired_hours' => (float) $expiringBalance,
                'payout_hours' => (float) $expiringBalance,
                'cutoff_date' => $cutoffDate->toDateString(),
                'transaction_id' => $transaction->id,
            ];
        });
    }

    /**
     * Get the cutoff date for expiry based on agreement type.
     */
    public function getExpiryDate(JourneyRule $rule): Carbon
    {
        $months = match ($rule->agreement_type ?? 'individual') {
            'collective' => min((int) $rule->hour_bank_expiry_months, 12),
            'monthly' => 1,
            default => min((int) $rule->hour_bank_expiry_months, 6), // individual: max 6 months
        };

        return now()->subMonths($months)->startOfDay();
    }

    /**
     * Get the total positive balance that has expired (entries before cutoff).
     */
    public function getExpiringBalance(int $userId, int $tenantId, Carbon $cutoffDate): float
    {
        // Sum positive hour bank deltas from journey entries before cutoff
        // that haven't already been expired
        $expiredTransactionDates = HourBankTransaction::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'expiry')
            ->pluck('reference_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        $entries = JourneyEntry::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('date', '<', $cutoffDate->toDateString())
            ->whereNotNull('hour_bank_balance')
            ->orderBy('date')
            ->get();

        if ($entries->isEmpty()) {
            return 0;
        }

        // Calculate the net positive balance accrued before cutoff
        $balance = 0;
        $previousBalance = 0;
        foreach ($entries as $entry) {
            $delta = (float) $entry->hour_bank_balance - $previousBalance;
            if ($delta > 0) {
                $balance += $delta;
            }
            $previousBalance = (float) $entry->hour_bank_balance;
        }

        // Subtract already expired amounts
        $alreadyExpired = (float) HourBankTransaction::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'expiry')
            ->sum(DB::raw('ABS(hours)'));

        return max(0, round($balance - $alreadyExpired, 2));
    }

    /**
     * Get total expired hours for a given month that haven't been paid out yet.
     * Used by payroll processing to convert expired hour bank into overtime payout.
     */
    public function processExpiryForPayroll(int $userId, string $referenceMonth): float
    {
        $start = Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (float) HourBankTransaction::where('user_id', $userId)
            ->where('type', 'expiry')
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('payout_payroll_id')
            ->sum('hours');
    }

    /**
     * Get current hour bank balance for a user.
     */
    private function getCurrentBalance(int $userId): float
    {
        return (float) (JourneyEntry::where('user_id', $userId)
            ->orderByDesc('date')
            ->value('hour_bank_balance') ?? 0);
    }
}
