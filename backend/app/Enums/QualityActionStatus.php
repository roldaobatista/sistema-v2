<?php

namespace App\Enums;

enum QualityActionStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case VERIFIED = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::IN_PROGRESS => 'Em Andamento',
            self::COMPLETED => 'Concluída',
            self::VERIFIED => 'Verificada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::IN_PROGRESS => 'warning',
            self::COMPLETED => 'success',
            self::VERIFIED => 'success',
        };
    }
}
