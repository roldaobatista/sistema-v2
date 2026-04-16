<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case SENT = 'sent';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::ISSUED => 'Emitida',
            self::SENT => 'Enviada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'default',
            self::ISSUED => 'success',
            self::SENT => 'info',
            self::CANCELLED => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }
}
