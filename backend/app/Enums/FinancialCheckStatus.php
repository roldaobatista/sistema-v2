<?php

namespace App\Enums;

enum FinancialCheckStatus: string
{
    case PENDING = 'pending';
    case DEPOSITED = 'deposited';
    case COMPENSATED = 'compensated';
    case RETURNED = 'returned';
    case CUSTODY = 'custody';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::DEPOSITED => 'Depositado',
            self::COMPENSATED => 'Compensado',
            self::RETURNED => 'Devolvido',
            self::CUSTODY => 'Em Custódia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::DEPOSITED => 'info',
            self::COMPENSATED => 'success',
            self::RETURNED => 'danger',
            self::CUSTODY => 'amber',
        };
    }
}
