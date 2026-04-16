<?php

namespace App\Enums;

enum DealStatus: string
{
    case OPEN = 'open';
    case WON = 'won';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::WON => 'Ganho',
            self::LOST => 'Perdido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::WON => 'success',
            self::LOST => 'danger',
        };
    }
}
