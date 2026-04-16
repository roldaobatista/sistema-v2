<?php

namespace App\Listeners;

use App\Events\WorkOrderStatusChanged;
use App\Traits\DispatchesPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleWorkOrderStatusChanged implements ShouldQueue
{
    use DispatchesPushNotification;

    public function handle(WorkOrderStatusChanged $event): void
    {
        $wo = $event->workOrder;
        $technicianId = $wo['technician']['id'] ?? null;

        if (! $technicianId) {
            return;
        }

        $woId = $wo['id'];
        $status = $wo['status'];

        $this->sendPush(
            $technicianId,
            "OS #{$woId} — Status alterado",
            "Status alterado para {$status}",
            ['url' => "/tech/os/{$woId}", 'type' => 'work_order.status_changed']
        );
    }
}
