<?php

namespace App\Enums;

enum ImportStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DONE = 'done';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';
    case PARTIALLY_ROLLED_BACK = 'partially_rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::DONE => 'Concluído',
            self::FAILED => 'Falhou',
            self::ROLLED_BACK => 'Desfeita',
            self::PARTIALLY_ROLLED_BACK => 'Parcialmente Desfeita',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::DONE => 'success',
            self::FAILED => 'danger',
            self::ROLLED_BACK => 'amber',
            self::PARTIALLY_ROLLED_BACK => 'amber',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::DONE, self::FAILED, self::ROLLED_BACK, self::PARTIALLY_ROLLED_BACK], true);
    }
}
