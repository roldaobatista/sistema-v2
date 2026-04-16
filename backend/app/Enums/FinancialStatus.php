<?php

namespace App\Enums;

enum FinancialStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case RENEGOTIATED = 'renegotiated';
    case RECEIVED = 'received';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PARTIAL => 'Parcial',
            self::PAID => 'Pago',
            self::OVERDUE => 'Vencido',
            self::CANCELLED => 'Cancelado',
            self::RENEGOTIATED => 'Renegociado',
            self::RECEIVED => 'Recebido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PARTIAL => 'info',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'default',
            self::RENEGOTIATED => 'purple',
            self::RECEIVED => 'success',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::PARTIAL, self::OVERDUE], true);
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED, self::RENEGOTIATED, self::RECEIVED], true);
    }
}
