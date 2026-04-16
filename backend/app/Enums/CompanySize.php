<?php

namespace App\Enums;

enum CompanySize: string
{
    case MICRO = 'micro';
    case PEQUENA = 'pequena';
    case MEDIA = 'media';
    case GRANDE = 'grande';

    public function label(): string
    {
        return match ($this) {
            self::MICRO => 'Microempresa',
            self::PEQUENA => 'Pequena',
            self::MEDIA => 'Média',
            self::GRANDE => 'Grande',
        };
    }
}
