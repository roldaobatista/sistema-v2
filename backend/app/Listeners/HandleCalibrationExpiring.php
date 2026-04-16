<?php

namespace App\Listeners;

use App\Events\CalibrationExpiring;
use App\Models\CrmActivity;
use App\Models\User;
use App\Notifications\CalibrationExpiryNotification;
use App\Traits\DispatchesPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleCalibrationExpiring implements ShouldQueue
{
    use DispatchesPushNotification;

    public function handle(CalibrationExpiring $event): void
    {
        $cal = $event->calibration;
        $days = $event->daysUntilExpiry;
        $equipment = $cal->equipment;
        $customer = $equipment?->customer;

        if (! $customer) {
            Log::info('HandleCalibrationExpiring: equipamento sem cliente', ['calibration_id' => $cal->id, 'equipment_id' => $equipment?->id]);

            return;
        }

        $tenantId = $cal->tenant_id ?? $equipment->tenant_id;
        app()->instance('current_tenant_id', $tenantId);
        $notifyUserId = $customer->assigned_seller_id ?? $equipment->responsible_user_id;

        if (! $notifyUserId) {
            Log::info('HandleCalibrationExpiring: sem usuário para notificar', ['calibration_id' => $cal->id, 'customer_id' => $customer->id]);

            return;
        }

        $user = User::find($notifyUserId);

        if (! $user) {
            Log::warning('HandleCalibrationExpiring: usuário não encontrado', ['user_id' => $notifyUserId, 'calibration_id' => $cal->id]);

            return;
        }

        try {
            $user->notify(new CalibrationExpiryNotification($equipment, $days, $tenantId));
        } catch (\Throwable $e) {
            Log::error('HandleCalibrationExpiring: Notification failed', ['calibration_id' => $cal->id, 'error' => $e->getMessage()]);
        }

        try {
            CrmActivity::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'user_id' => $notifyUserId,
                'type' => 'follow_up',
                'title' => "Recalibração — {$equipment->serial_number}",
                'description' => "Calibração vence em {$days} dias. Contatar cliente para agendar recalibração.",
                'scheduled_at' => now()->addDays(1),
            ]);
        } catch (\Throwable $e) {
            Log::error('HandleCalibrationExpiring: CrmActivity failed', ['calibration_id' => $cal->id, 'error' => $e->getMessage()]);
        }

        $equipmentName = $equipment->name ?? $equipment->serial_number ?? "ID {$equipment->id}";
        $expiryDate = $cal->expires_at?->format('d/m/Y') ?? $cal->calibration_date?->addYear()->format('d/m/Y') ?? '—';

        $this->sendPushToRole(
            $tenantId,
            'gerente',
            'Calibração vencendo',
            "{$equipmentName} — vence em {$expiryDate}",
            ['type' => 'calibration.expiring']
        );
    }
}
