<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case OPEN = 'open';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::PROCESSING => 'Em Processamento',
            self::COMPLETED => 'Concluído',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::PROCESSING => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
