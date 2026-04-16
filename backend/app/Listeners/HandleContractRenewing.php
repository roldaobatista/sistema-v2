<?php

namespace App\Listeners;

use App\Events\ContractRenewing;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleContractRenewing implements ShouldQueue
{
    public function handle(ContractRenewing $event): void
    {
        $contract = $event->contract;
        $days = $event->daysUntilEnd;

        app()->instance('current_tenant_id', $contract->tenant_id);

        $notifyUserId = $contract->assigned_to ?? $contract->created_by;

        if (! $notifyUserId) {
            Log::info('HandleContractRenewing: sem usuário para notificar', ['contract_id' => $contract->id]);

            return;
        }

        try {
            Notification::create([
                'tenant_id' => $contract->tenant_id,
                'user_id' => $notifyUserId,
                'type' => 'contract_renewing',
                'title' => 'Contrato Próximo do Vencimento',
                'message' => "O contrato \"{$contract->name}\" vence em {$days} dias. Cliente: {$contract->customer?->name}.",
                'data' => [
                    'recurring_contract_id' => $contract->id,
                    'customer_id' => $contract->customer_id,
                    'days_until_end' => $days,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('HandleContractRenewing failed', ['contract_id' => $contract->id, 'error' => $e->getMessage()]);
        }
    }
}
