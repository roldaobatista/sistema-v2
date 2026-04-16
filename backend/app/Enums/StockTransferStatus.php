<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case PENDING_ACCEPTANCE = 'pending_acceptance';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_ACCEPTANCE => 'Aguard. Aceite',
            self::ACCEPTED => 'Aceita',
            self::REJECTED => 'Rejeitada',
            self::CANCELLED => 'Cancelada',
            self::COMPLETED => 'Concluída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_ACCEPTANCE => 'warning',
            self::ACCEPTED => 'info',
            self::REJECTED => 'danger',
            self::CANCELLED => 'danger',
            self::COMPLETED => 'success',
        };
    }
}
