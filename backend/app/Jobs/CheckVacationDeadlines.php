<?php

namespace App\Jobs;

use App\Events\VacationDeadlineApproaching;
use App\Models\VacationBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckVacationDeadlines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        $thresholds = [60, 30];

        foreach ($thresholds as $days) {
            $balances = VacationBalance::whereNotNull('deadline')
                ->whereDate('deadline', now()->addDays($days)->toDateString())
                ->where('status', '!=', 'taken')
                ->whereRaw('(total_days - taken_days - sold_days) > 0')
                ->get();

            foreach ($balances as $balance) {
                try {
                    app()->instance('current_tenant_id', $balance->tenant_id);
                    VacationDeadlineApproaching::dispatch($balance, $days);
                } catch (\Throwable $e) {
                    Log::warning("CheckVacationDeadlines: falha para balance #{$balance->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CheckVacationDeadlines job failed', ['error' => $e->getMessage()]);
    }
}
