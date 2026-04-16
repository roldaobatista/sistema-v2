<?php

namespace App\Modules\Metrologia\Listeners;

use App\Models\EquipmentCalibration;
use App\Models\Notification;
use App\Models\WorkOrder;
use App\Modules\OrdemServico\Events\OrdemServicoFinalizadaEvent;
use App\Services\CalibrationCertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Gera rascunho do certificado de calibração para cada calibração da OS finalizada.
 * Escuta OrdemServicoFinalizadaEvent; módulo de OS não depende de Metrologia.
 */
class GerarRascunhoCertificadoListener implements ShouldQueue
{
    public function __construct(
        private CalibrationCertificateService $certificateService,
    ) {}

    public function handle(OrdemServicoFinalizadaEvent $event): void
    {
        $payload = $event->payload;

        $calibrations = EquipmentCalibration::withoutGlobalScope('tenant')
            ->where('work_order_id', $payload->workOrderId)
            ->where('tenant_id', $payload->tenantId)
            ->get();

        foreach ($calibrations as $calibration) {
            try {
                $this->certificateService->generateAndStore($calibration);
            } catch (\Throwable $e) {
                Log::warning('Metrologia: falha ao gerar rascunho de certificado na OS finalizada', [
                    'work_order_id' => $payload->workOrderId,
                    'equipment_calibration_id' => $calibration->id,
                    'error' => $e->getMessage(),
                ]);

                // Notificar responsável da OS sobre falha na geração do certificado
                try {
                    $wo = WorkOrder::find($payload->workOrderId);
                    $userId = $wo?->assigned_to ?? $wo?->created_by;
                    if ($userId && $payload->tenantId) {
                        Notification::notify(
                            $payload->tenantId,
                            $userId,
                            'certificate_error',
                            'Falha ao gerar certificado de calibração',
                            [
                                'message' => "Certificado para calibração #{$calibration->id} na OS {$wo->business_number} não foi gerado automaticamente. Gere manualmente pelo módulo de calibração.",
                                'icon' => 'file-warning',
                                'color' => 'warning',
                                'data' => [
                                    'work_order_id' => $payload->workOrderId,
                                    'equipment_calibration_id' => $calibration->id,
                                ],
                            ]
                        );
                    }
                } catch (\Throwable) {
                    // Não deixar a notificação falhar novamente
                }
            }
        }
    }
}
