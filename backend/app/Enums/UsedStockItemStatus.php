<?php

namespace App\Enums;

enum UsedStockItemStatus: string
{
    case PENDING_RETURN = 'pending_return';
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case RETURNED = 'returned';
    case WRITTEN_OFF_NO_RETURN = 'written_off_no_return';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_RETURN => 'Aguard. Devolução',
            self::PENDING_CONFIRMATION => 'Aguard. Confirmação',
            self::RETURNED => 'Devolvido',
            self::WRITTEN_OFF_NO_RETURN => 'Baixado (sem retorno)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_RETURN => 'warning',
            self::PENDING_CONFIRMATION => 'amber',
            self::RETURNED => 'success',
            self::WRITTEN_OFF_NO_RETURN => 'danger',
        };
    }
}
