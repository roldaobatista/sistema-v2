<?php

namespace App\Enums;

enum CommissionSettlementStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PAID = 'paid';

    public static function normalizeFilter(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            self::CLOSED->value => [
                self::CLOSED->value,
                self::PENDING_APPROVAL->value,
            ],
            self::PENDING_APPROVAL->value => [
                self::CLOSED->value,
                self::PENDING_APPROVAL->value,
            ],
            default => [$normalized],
        };
    }

    public static function canonicalValue(self|string|null $value): ?string
    {
        if ($value instanceof self) {
            $value = $value->value;
        }

        if (! $value) {
            return null;
        }

        return match (mb_strtolower(trim($value))) {
            self::PENDING_APPROVAL->value => self::CLOSED->value,
            default => mb_strtolower(trim($value)),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::CLOSED => 'Fechado',
            self::PENDING_APPROVAL => 'Aguard. Aprovação (legado)',
            self::APPROVED => 'Aprovado',
            self::REJECTED => 'Rejeitado',
            self::PAID => 'Pago',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::CLOSED => 'info',
            self::PENDING_APPROVAL => 'amber',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PAID => 'success',
        };
    }
}
