<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateFinancialPenalties extends Command
{
    protected $signature = 'financial:calculate-penalties';

    protected $description = 'Calculate interest and penalties for overdue receivables';

    public function handle(): int
    {
        $updated = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use (&$updated) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $overdueReceivables = AccountReceivable::whereIn('status', [AccountReceivable::STATUS_PENDING, AccountReceivable::STATUS_PARTIAL, AccountReceivable::STATUS_OVERDUE])
                    ->whereDate('due_date', '<', Carbon::today())
                    ->where(function ($q) {
                        $q->whereNull('penalties_calculated_at')
                            ->orWhereDate('penalties_calculated_at', '<', Carbon::today());
                    })
                    ->get();

                $monthlyInterestRate = $tenant->interest_rate ?? 1.0;
                $penaltyRate = $tenant->penalty_rate ?? 2.0;

                foreach ($overdueReceivables as $receivable) {
                    try {
                        $daysOverdue = Carbon::parse($receivable->due_date)->diffInDays(Carbon::today());

                        $dailyRate = bcdiv(bcdiv((string) $monthlyInterestRate, '30', 8), '100', 8);
                        $interest = bcmul(bcmul((string) $receivable->amount, $dailyRate, 8), (string) $daysOverdue, 2);
                        $penalty = $daysOverdue > 0
                            ? bcmul((string) $receivable->amount, bcdiv((string) $penaltyRate, '100', 8), 2)
                            : '0.00';

                        DB::transaction(function () use ($receivable, $interest, $penalty) {
                            $receivable->update([
                                'interest_amount' => $interest,
                                'penalty_amount' => $penalty,
                                'total_with_penalties' => bcadd(bcadd((string) $receivable->amount, $interest, 2), $penalty, 2),
                                'penalties_calculated_at' => now(),
                            ]);
                        });

                        $updated++;
                    } catch (\Throwable $e) {
                        Log::error('Penalty calculation failed', [
                            'receivable_id' => $receivable->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("CalculateFinancialPenalties: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        });

        $this->info("Updated penalties for {$updated} overdue receivables.");

        return self::SUCCESS;
    }
}
