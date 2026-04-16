<?php

namespace App\Enums;

enum CommissionEventStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case REVERSED = 'reversed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::APPROVED => 'Aprovado',
            self::PAID => 'Pago',
            self::REVERSED => 'Estornado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::PAID => 'success',
            self::REVERSED => 'danger',
            self::CANCELLED => 'danger',
        };
    }

    /** Returns allowed transitions from this status */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::APPROVED, self::CANCELLED],
            self::APPROVED => [self::PAID, self::REVERSED, self::PENDING],
            self::PAID => [self::REVERSED],
            self::REVERSED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
