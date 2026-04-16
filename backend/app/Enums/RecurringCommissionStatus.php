<?php

namespace App\Enums;

enum RecurringCommissionStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::PAUSED => 'Pausada',
            self::TERMINATED => 'Encerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::PAUSED => 'warning',
            self::TERMINATED => 'danger',
        };
    }
}
