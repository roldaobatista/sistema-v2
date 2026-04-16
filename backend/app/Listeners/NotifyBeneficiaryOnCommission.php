<?php

namespace App\Listeners;

use App\Events\CommissionGenerated;
use App\Models\Notification;
use App\Traits\DispatchesPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyBeneficiaryOnCommission implements ShouldQueue
{
    use DispatchesPushNotification;

    public function handle(CommissionGenerated $event): void
    {
        $commission = $event->commission;

        app()->instance('current_tenant_id', $commission->tenant_id);

        try {
            Notification::notify(
                $commission->tenant_id,
                $commission->user_id,
                'commission_generated',
                "Comiss\u{00e3}o Gerada",
                [
                    'message' => "Comiss\u{00e3}o de R$ ".number_format((float) $commission->commission_amount, 2, ',', '.')." gerada referente \u{00e0} OS #{$commission->work_order_id}.",
                    'icon' => 'dollar-sign',
                    'color' => 'success',
                    'data' => ['commission_id' => $commission->id, 'work_order_id' => $commission->work_order_id],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("NotifyBeneficiaryOnCommission: falha para commission #{$commission->id}", ['error' => $e->getMessage()]);
        }

        // Push notification para o beneficiário
        $this->sendPush(
            $commission->user_id,
            'Comissão gerada',
            'Nova comissão de R$ '.number_format((float) $commission->commission_amount, 2, ',', '.'),
            ['type' => 'commission.generated', 'commission_id' => $commission->id],
        );
    }
}
