<?php

namespace App\Modules\OrdemServico\DTO;

/**
 * Payload para o evento OrdemServicoFinalizadaEvent.
 * Contém apenas IDs; o listener de Metrologia resolve calibrações por work_order_id.
 */
final readonly class OrdemServicoFinalizadaPayload
{
    public function __construct(
        public int $workOrderId,
        public int $tenantId,
        public int $completedByUserId,
    ) {}

    public static function fromWorkOrder(object $workOrder, object $user): self
    {
        return new self(
            workOrderId: (int) $workOrder->id,
            tenantId: (int) $workOrder->tenant_id,
            completedByUserId: (int) $user->id,
        );
    }
}
