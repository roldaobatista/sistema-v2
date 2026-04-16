<?php

namespace App\Observers;

use App\Enums\CommissionEventStatus;
use App\Enums\FinancialStatus;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Support\Decimal;
use Illuminate\Support\Facades\Log;

class AccountReceivableObserver
{
    /**
     * Handle the AccountReceivable "updated" event.
     */
    public function updated(AccountReceivable $accountReceivable): void
    {
        $statusValue = $accountReceivable->status instanceof FinancialStatus
            ? $accountReceivable->status->value
            : $accountReceivable->status;

        if ($accountReceivable->wasChanged('status') && $statusValue === FinancialStatus::CANCELLED->value) {
            $this->handleCancellation($accountReceivable);
        }
    }

    protected function handleCancellation(AccountReceivable $accountReceivable): void
    {
        $events = CommissionEvent::where('account_receivable_id', $accountReceivable->id)->get();

        if ($events->isEmpty() && $accountReceivable->work_order_id) {
            $events = CommissionEvent::where('work_order_id', $accountReceivable->work_order_id)
                ->whereNotIn('status', [CommissionEventStatus::CANCELLED->value, CommissionEventStatus::REVERSED->value])
                ->get();
        }

        foreach ($events as $event) {
            $eventStatusValue = $event->status instanceof CommissionEventStatus ? $event->status->value : $event->status;

            if ($eventStatusValue === CommissionEventStatus::PENDING->value || $eventStatusValue === CommissionEventStatus::APPROVED->value) {
                $event->update([
                    'status' => CommissionEventStatus::CANCELLED->value,
                    'notes' => trim($event->notes.' (Cancelado auto via Financeiro).'),
                ]);
            } elseif ($eventStatusValue === CommissionEventStatus::PAID->value) {
                $reversal = $event->replicate();
                $reversal->commission_amount = Decimal::string(-abs((float) $event->commission_amount));
                $reversal->base_amount = Decimal::string(-abs((float) $event->base_amount));
                $reversal->status = CommissionEventStatus::APPROVED;
                $reversal->settlement_id = null;
                $reversal->notes = "Estorno automático ref evento ID {$event->id}";
                $reversal->save();

                $event->update([
                    'status' => CommissionEventStatus::REVERSED->value,
                ]);

                Log::info("AccountReceivableObserver: Gerado estorno de comissão para o evento {$event->id}.");
            }
        }
    }
}
