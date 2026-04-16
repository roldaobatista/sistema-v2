<?php

namespace App\Events;

use App\Models\FiscalNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando uma nota fiscal é autorizada (ex.: via webhook assíncrono da API externa).
 * Listeners podem liberar a Ordem de Serviço (status Faturada) ou disparar envio do Certificado de Calibração.
 */
class FiscalNoteAuthorized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FiscalNote $fiscalNote,
    ) {}
}
