<?php

namespace App\Listeners;

use App\Events\ExpenseLimitExceeded;
use App\Models\Notification;
use App\Traits\DispatchesPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyManagerOnExpenseLimit implements ShouldQueue
{
    use DispatchesPushNotification;

    public function handle(ExpenseLimitExceeded $event): void
    {
        $expense = $event->expense;

        app()->instance('current_tenant_id', $expense->tenant_id);

        try {
            $pct = bccomp((string) $event->limit, '0', 2) > 0
                ? (int) bcmul(bcdiv((string) $event->currentTotal, (string) $event->limit, 4), '100', 0)
                : 0;

            $managerId = $expense->approved_by ?? $expense->creator?->manager_id;
            if (! $managerId) {
                return;
            }

            Notification::notify(
                $expense->tenant_id,
                $managerId,
                'expense_limit_exceeded',
                'Limite de Despesa Excedido',
                [
                    'message' => "Despesas de {$expense->category} atingiram {$pct}% do limite (R$ ".number_format($event->currentTotal, 2, ',', '.').' / R$ '.number_format($event->limit, 2, ',', '.').').',
                    'icon' => 'alert-triangle',
                    'color' => 'warning',
                    'data' => ['expense_id' => $expense->id, 'user_id' => $expense->user_id],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('NotifyManagerOnExpenseLimit failed', ['expense_id' => $expense->id, 'error' => $e->getMessage()]);
        }

        // Push notification para gerentes
        $warningMessage = "Despesas de {$expense->category} atingiram "
            .(isset($pct) ? "{$pct}%" : '')
            .' do limite (R$ '.number_format($event->currentTotal, 2, ',', '.')
            .' / R$ '.number_format($event->limit, 2, ',', '.').')';

        $this->sendPushToRole(
            $expense->tenant_id,
            'gerente',
            'Limite de despesas excedido',
            $warningMessage,
            ['type' => 'expense.limit_exceeded', 'expense_id' => $expense->id],
        );
    }
}
