<?php

namespace App\Services\Fiscal\Contracts;

use App\Services\Fiscal\DTO\NFeDTO;
use App\Services\Fiscal\FiscalResult;

/**
 * Gateway de emissão fiscal (API externa).
 * O Core e o módulo Financeiro dependem apenas desta interface;
 * a implementação concreta (qual fornecedor) fica isolada no adapter.
 */
interface FiscalGatewayInterface
{
    /**
     * Emite NF-e junto ao fornecedor externo.
     */
    public function emitirNFe(NFeDTO $data): FiscalResult;

    /**
     * Consulta o status de um documento pelo protocolo/referência.
     */
    public function consultarStatus(string $protocolo): FiscalResult;
}
