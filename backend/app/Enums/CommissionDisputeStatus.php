<?php

namespace App\Enums;

enum CommissionDisputeStatus: string
{
    case OPEN = 'open';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case RESOLVED = 'resolved';

    public static function normalizeFilter(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        return match (mb_strtolower(trim($value))) {
            self::RESOLVED->value => [
                self::ACCEPTED->value,
                self::REJECTED->value,
                self::RESOLVED->value,
            ],
            self::OPEN->value,
            self::ACCEPTED->value,
            self::REJECTED->value => [mb_strtolower(trim($value))],
            default => [mb_strtolower(trim($value))],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::ACCEPTED => 'Aceita',
            self::REJECTED => 'Rejeitada',
            self::RESOLVED => 'Resolvida (legado)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::RESOLVED => 'info',
        };
    }
}
