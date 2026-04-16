<?php

namespace App\Enums;

enum FiscalNoteStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case AUTHORIZED = 'authorized';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::AUTHORIZED => 'Autorizada',
            self::CANCELLED => 'Cancelada',
            self::REJECTED => 'Rejeitada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::AUTHORIZED => 'success',
            self::CANCELLED => 'danger',
            self::REJECTED => 'danger',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::AUTHORIZED, self::CANCELLED], true);
    }
}
