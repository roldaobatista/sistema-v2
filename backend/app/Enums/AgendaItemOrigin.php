<?php

namespace App\Enums;

enum AgendaItemOrigin: string
{
    case MANUAL = 'manual';
    case AUTO = 'auto';
    case JOB = 'job';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::AUTO => 'Automático',
            self::JOB => 'Job/Sistema',
        };
    }
}
