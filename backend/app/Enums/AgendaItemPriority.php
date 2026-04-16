<?php

namespace App\Enums;

enum AgendaItemPriority: string
{
    case BAIXA = 'low';
    case MEDIA = 'medium';
    case ALTA = 'high';
    case URGENTE = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::BAIXA => 'Baixa',
            self::MEDIA => 'Média',
            self::ALTA => 'Alta',
            self::URGENTE => 'Urgente',
        };
    }
}
