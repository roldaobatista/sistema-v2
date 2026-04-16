<?php

namespace App\Enums;

enum BankStatementEntryStatus: string
{
    case PENDING = 'pending';
    case MATCHED = 'matched';
    case IGNORED = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::MATCHED => 'Conciliado',
            self::IGNORED => 'Ignorado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::MATCHED => 'success',
            self::IGNORED => 'default',
        };
    }
}
