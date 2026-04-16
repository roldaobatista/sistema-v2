<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case In = 'in';
    case Out = 'out';
    case Reserve = 'reserve';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Saída',
            self::In => 'Entrada',
            self::Out => 'Saída',
            self::Reserve => 'Reserva',
            self::Return => 'Devolução',
            self::Adjustment => 'Ajuste',
            self::Transfer => 'Transferência',
        };
    }

    public function affectsStock(): int
    {
        return match ($this) {
            self::Entry, self::Return, self::In => 1,
            self::Exit, self::Reserve, self::Out => -1,
            self::Adjustment, self::Transfer => 0, // Adjustment uses sign; Transfer is special
        };
    }
}
