<?php

namespace App\Listeners;

use App\Events\ServiceCallCreated;
use App\Traits\DispatchesPushNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleServiceCallCreated implements ShouldQueue
{
    use DispatchesPushNotification;

    public function handle(ServiceCallCreated $event): void
    {
        $serviceCall = $event->serviceCall;
        $tenantId = $serviceCall->tenant_id;
        $id = $serviceCall->id;
        $subject = $serviceCall->subject ?? 'Novo chamado registrado';

        $data = ['url' => "/chamados/{$id}", 'type' => 'service_call.created'];

        $this->sendPushToRole($tenantId, 'coordenador', "Novo chamado #{$id}", $subject, $data);
        $this->sendPushToRole($tenantId, 'atendimento', "Novo chamado #{$id}", $subject, $data);
    }
}
