<?php

namespace App\Enums;

enum FundTransferStatus: string
{
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Concluída',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
