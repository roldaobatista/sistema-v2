<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case PENDING = 'pending';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REIMBURSED = 'reimbursed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::REVIEWED => 'Conferido',
            self::APPROVED => 'Aprovado',
            self::REJECTED => 'Rejeitado',
            self::REIMBURSED => 'Reembolsado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::REVIEWED => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::REIMBURSED => 'success',
        };
    }

    public function isApproved(): bool
    {
        return in_array($this, [self::APPROVED, self::REIMBURSED], true);
    }
}
