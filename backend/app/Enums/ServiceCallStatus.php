<?php

namespace App\Enums;

enum ServiceCallStatus: string
{
    case PENDING_SCHEDULING = 'pending_scheduling';
    case SCHEDULED = 'scheduled';
    case RESCHEDULED = 'rescheduled';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';
    case IN_PROGRESS = 'in_progress';
    case CONVERTED_TO_OS = 'converted_to_os';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_SCHEDULING => 'Pendente de Agendamento',
            self::SCHEDULED => 'Agendado',
            self::RESCHEDULED => 'Reagendado',
            self::AWAITING_CONFIRMATION => 'Aguardando Confirmação',
            self::IN_PROGRESS => 'Em Andamento',
            self::CONVERTED_TO_OS => 'Convertido em OS',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_SCHEDULING => 'bg-blue-100 text-blue-700',
            self::SCHEDULED => 'bg-amber-100 text-amber-700',
            self::RESCHEDULED => 'bg-orange-100 text-orange-700',
            self::AWAITING_CONFIRMATION => 'bg-cyan-100 text-cyan-700',
            self::IN_PROGRESS => 'bg-purple-100 text-purple-700',
            self::CONVERTED_TO_OS => 'bg-emerald-100 text-emerald-700',
            self::CANCELLED => 'bg-red-100 text-red-700',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    /** @return list<self> */
    public static function activeStatuses(): array
    {
        return [
            self::PENDING_SCHEDULING,
            self::SCHEDULED,
            self::RESCHEDULED,
            self::AWAITING_CONFIRMATION,
            self::IN_PROGRESS,
        ];
    }

    /** @return list<string> */
    public static function unattendedValues(): array
    {
        return [
            self::PENDING_SCHEDULING->value,
            self::SCHEDULED->value,
            self::RESCHEDULED->value,
            self::AWAITING_CONFIRMATION->value,
        ];
    }

    /** Returns allowed transitions from this status */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING_SCHEDULING => [self::SCHEDULED, self::CANCELLED],
            self::SCHEDULED => [self::RESCHEDULED, self::AWAITING_CONFIRMATION, self::IN_PROGRESS, self::CONVERTED_TO_OS, self::CANCELLED],
            self::RESCHEDULED => [self::SCHEDULED, self::AWAITING_CONFIRMATION, self::CANCELLED],
            self::AWAITING_CONFIRMATION => [self::SCHEDULED, self::IN_PROGRESS, self::CONVERTED_TO_OS, self::CANCELLED],
            self::IN_PROGRESS => [self::CONVERTED_TO_OS, self::CANCELLED],
            self::CONVERTED_TO_OS => [],
            self::CANCELLED => [self::PENDING_SCHEDULING],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
