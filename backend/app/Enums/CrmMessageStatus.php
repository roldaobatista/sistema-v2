<?php

namespace App\Enums;

enum CrmMessageStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::SENT => 'Enviada',
            self::DELIVERED => 'Entregue',
            self::READ => 'Lida',
            self::FAILED => 'Falhou',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SENT => 'info',
            self::DELIVERED => 'success',
            self::READ => 'success',
            self::FAILED => 'danger',
        };
    }
}
