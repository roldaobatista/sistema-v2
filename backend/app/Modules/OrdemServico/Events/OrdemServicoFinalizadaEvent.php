<?php

namespace App\Modules\OrdemServico\Events;

use App\Modules\OrdemServico\DTO\OrdemServicoFinalizadaPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando uma Ordem de Serviço é finalizada (status = completed).
 * Payload é um DTO; módulos consumidores (ex.: Metrologia) não criam acoplamento com OS.
 */
class OrdemServicoFinalizadaEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrdemServicoFinalizadaPayload $payload,
    ) {}
}
