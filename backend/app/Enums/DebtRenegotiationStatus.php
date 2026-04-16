<?php

namespace App\Enums;

enum DebtRenegotiationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::APPROVED => 'Aprovada',
            self::REJECTED => 'Rejeitada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'danger',
        };
    }
}
