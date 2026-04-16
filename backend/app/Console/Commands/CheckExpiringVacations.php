<?php

namespace App\Console\Commands;

use App\Models\VacationBalance;
use App\Notifications\VacationExpirationNotification;
use Illuminate\Console\Command;

class CheckExpiringVacations extends Command
{
    protected $signature = 'hr:check-expiring-vacations';

    protected $description = 'Notifica sobre férias prestes a vencer (60, 30 e 15 dias). Férias vencidas = multa dobrada (CLT art. 137).';

    public function handle(): int
    {
        $thresholds = [60, 30, 15];
        $notified = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $balances = VacationBalance::with('user')
                ->where('deadline', $targetDate)
                ->where('remaining_days', '>', 0)
                ->whereIn('status', ['available', 'partially_taken'])
                ->get();

            foreach ($balances as $balance) {
                if ($balance->user) {
                    $balance->user->notify(new VacationExpirationNotification($balance, $days));
                    $notified++;
                }
            }
        }

        // Marcar férias expiradas
        VacationBalance::where('deadline', '<', now()->toDateString())
            ->where('remaining_days', '>', 0)
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);

        $this->info("Notificados: {$notified} férias vencendo.");

        return self::SUCCESS;
    }
}
