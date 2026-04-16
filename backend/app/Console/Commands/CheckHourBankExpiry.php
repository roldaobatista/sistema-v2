<?php

namespace App\Console\Commands;

use App\Models\HourBankTransaction;
use App\Models\JourneyRule;
use Illuminate\Console\Command;

class CheckHourBankExpiry extends Command
{
    protected $signature = 'hr:check-hour-bank-expiry';

    protected $description = 'Verifica e expira saldos de banco de horas conforme prazo do acordo (CLT art. 59 §5)';

    public function handle(): int
    {
        $expired = 0;

        // Buscar regras de jornada com banco de horas ativo
        $rules = JourneyRule::where('uses_hour_bank', true)
            ->whereNotNull('hour_bank_months')
            ->get();

        foreach ($rules as $rule) {
            $expiryDate = now()->subMonths($rule->hour_bank_months)->toDateString();

            // Buscar transações de crédito não expiradas que passaram do prazo
            $transactions = HourBankTransaction::where('type', 'credit')
                ->whereNull('expired_at')
                ->where('reference_date', '<=', $expiryDate)
                ->where('balance_after', '>', 0)
                ->get();

            foreach ($transactions as $transaction) {
                // Criar transação de expiração
                $currentBalance = HourBankTransaction::where('user_id', $transaction->user_id)
                    ->latest('id')
                    ->value('balance_after') ?? 0;

                if ($currentBalance > 0) {
                    HourBankTransaction::create([
                        'tenant_id' => $transaction->tenant_id,
                        'user_id' => $transaction->user_id,
                        'type' => 'expiry',
                        'hours' => -min($transaction->hours, $currentBalance),
                        'balance_before' => $currentBalance,
                        'balance_after' => max(0, $currentBalance - $transaction->hours),
                        'reference_date' => now()->toDateString(),
                        'expired_at' => now(),
                        'notes' => "Expiração automática - prazo de {$rule->hour_bank_months} meses",
                    ]);

                    $transaction->update(['expired_at' => now()]);
                    $expired++;
                }
            }
        }

        $this->info("Expiradas: {$expired} transações de banco de horas.");

        return self::SUCCESS;
    }
}
