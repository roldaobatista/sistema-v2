<?php

namespace App\Listeners;

use App\Events\CalibrationCompleted;
use App\Services\CalibrationCertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerCertificateGeneration implements ShouldQueue
{
    public function __construct(
        private CalibrationCertificateService $certificateService,
    ) {}

    public function handle(CalibrationCompleted $event): void
    {
        $workOrder = $event->workOrder;
        $equipmentId = $event->equipmentId;

        app()->instance('current_tenant_id', $workOrder->tenant_id);

        try {
            $this->certificateService->generateFromWorkOrder($workOrder, $equipmentId);
            Log::info("CalibrationCompleted: Certificate generated for WO #{$workOrder->id}, Equipment #{$equipmentId}");
        } catch (\Throwable $e) {
            Log::error("CalibrationCompleted: Failed to generate certificate for WO #{$workOrder->id}: {$e->getMessage()}");
        }
    }
}
