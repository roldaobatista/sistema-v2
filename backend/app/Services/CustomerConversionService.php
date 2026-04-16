<?php

namespace App\Services;

use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

/**
 * Converte um lead CRM em cliente quando a primeira OS é concluída.
 * Integra com o HandleWorkOrderCompletion Listener.
 */
class CustomerConversionService
{
    /**
     * Se a OS pertence a um cliente vinculado a deals CRM abertos,
     * e é a primeira OS concluída desse cliente, marca o deal como "ganho".
     */
    public function convertLeadIfFirstOS(WorkOrder $workOrder): void
    {
        if (! $workOrder->customer_id || ! $workOrder->tenant_id) {
            return;
        }

        // Verificar se é a PRIMEIRA OS concluída deste cliente
        $completedOsCount = WorkOrder::where('tenant_id', $workOrder->tenant_id)
            ->where('customer_id', $workOrder->customer_id)
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->count();

        // Se existem mais de 1 OS concluída (incluindo esta), não é a primeira
        if ($completedOsCount > 1) {
            return;
        }

        // Buscar deals CRM abertos vinculados a este cliente
        $openDeals = CrmDeal::where('tenant_id', $workOrder->tenant_id)
            ->where('customer_id', $workOrder->customer_id)
            ->where('status', CrmDeal::STATUS_OPEN)
            ->get();

        if ($openDeals->isEmpty()) {
            return;
        }

        foreach ($openDeals as $deal) {
            try {
                // Vincular a OS ao deal se não vinculado
                if (! $deal->work_order_id) {
                    $deal->update(['work_order_id' => $workOrder->id]);
                }

                $deal->markAsWon();

                Log::info("Deal CRM #{$deal->id} marcado como ganho (1ª OS concluída)", [
                    'deal_id' => $deal->id,
                    'customer_id' => $workOrder->customer_id,
                    'work_order_id' => $workOrder->id,
                ]);

                // Notificar o vendedor/responsável do deal
                if ($deal->assigned_to) {
                    Notification::notify(
                        $workOrder->tenant_id,
                        $deal->assigned_to,
                        'deal_won_auto',
                        'Deal Ganho Automaticamente',
                        [
                            'message' => "O deal \"{$deal->title}\" foi marcado como ganho automaticamente após a conclusão da OS #{$workOrder->business_number}.",
                            'icon' => 'trophy',
                            'color' => 'success',
                            'data' => [
                                'deal_id' => $deal->id,
                                'work_order_id' => $workOrder->id,
                            ],
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::error("Falha ao converter deal CRM #{$deal->id}: {$e->getMessage()}");
            }
        }

        // Atualizar flag do cliente (se existir campo is_lead / customer_type)
        $customer = Customer::find($workOrder->customer_id);
        if ($customer && $customer->customer_type === 'lead') {
            $customer->update(['customer_type' => 'client']);
            Log::info("Customer #{$customer->id} promovido de lead para client");
        }
    }
}
