<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Agendado',
            self::CONFIRMED => 'Confirmado',
            self::COMPLETED => 'Concluído',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'info',
            self::CONFIRMED => 'brand',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
