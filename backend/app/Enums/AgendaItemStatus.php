<?php

namespace App\Enums;

enum AgendaItemStatus: string
{
    case ABERTO = 'open';
    case EM_ANDAMENTO = 'in_progress';
    case AGUARDANDO = 'waiting';
    case CONCLUIDO = 'completed';
    case CANCELADO = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ABERTO => 'Aberto',
            self::EM_ANDAMENTO => 'Em Andamento',
            self::AGUARDANDO => 'Aguardando',
            self::CONCLUIDO => 'Concluído',
            self::CANCELADO => 'Cancelado',
        };
    }
}
