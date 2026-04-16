<?php

namespace App\Enums;

enum CustomerRating: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A — Alto Potencial',
            self::B => 'B — Médio Potencial',
            self::C => 'C — Baixo Potencial',
            self::D => 'D — Inativo',
        };
    }
}
