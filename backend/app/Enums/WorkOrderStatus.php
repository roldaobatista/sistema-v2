<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case OPEN = 'open';
    case AWAITING_DISPATCH = 'awaiting_dispatch';
    case IN_DISPLACEMENT = 'in_displacement';
    case DISPLACEMENT_PAUSED = 'displacement_paused';
    case AT_CLIENT = 'at_client';
    case IN_SERVICE = 'in_service';
    case SERVICE_PAUSED = 'service_paused';
    case AWAITING_RETURN = 'awaiting_return';
    case IN_RETURN = 'in_return';
    case RETURN_PAUSED = 'return_paused';
    case WAITING_PARTS = 'waiting_parts';
    case WAITING_APPROVAL = 'waiting_approval';
    case COMPLETED = 'completed';
    case DELIVERED = 'delivered';
    case INVOICED = 'invoiced';
    case CANCELLED = 'cancelled';

    /** @deprecated Use IN_SERVICE. Kept for backward compat with existing data. */
    case IN_PROGRESS = 'in_progress';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::AWAITING_DISPATCH => 'Aguard. Despacho',
            self::IN_DISPLACEMENT => 'Em Deslocamento',
            self::DISPLACEMENT_PAUSED => 'Desloc. Pausado',
            self::AT_CLIENT => 'No Cliente',
            self::IN_SERVICE => 'Em Serviço',
            self::SERVICE_PAUSED => 'Serviço Pausado',
            self::AWAITING_RETURN => 'Serviço Concluído',
            self::IN_RETURN => 'Em Retorno',
            self::RETURN_PAUSED => 'Retorno Pausado',
            self::WAITING_PARTS => 'Aguard. Peças',
            self::WAITING_APPROVAL => 'Aguard. Aprovação',
            self::COMPLETED => 'Finalizada',
            self::DELIVERED => 'Entregue',
            self::INVOICED => 'Faturada',
            self::CANCELLED => 'Cancelada',
            self::IN_PROGRESS => 'Em Andamento',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::AWAITING_DISPATCH => 'amber',
            self::IN_DISPLACEMENT => 'cyan',
            self::DISPLACEMENT_PAUSED => 'amber',
            self::AT_CLIENT => 'info',
            self::IN_SERVICE => 'warning',
            self::SERVICE_PAUSED => 'amber',
            self::AWAITING_RETURN => 'teal',
            self::IN_RETURN => 'cyan',
            self::RETURN_PAUSED => 'amber',
            self::WAITING_PARTS => 'warning',
            self::WAITING_APPROVAL => 'brand',
            self::COMPLETED => 'success',
            self::DELIVERED => 'success',
            self::INVOICED => 'brand',
            self::CANCELLED => 'danger',
            self::IN_PROGRESS => 'warning',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::IN_DISPLACEMENT, self::DISPLACEMENT_PAUSED,
            self::AT_CLIENT, self::IN_SERVICE, self::SERVICE_PAUSED,
            self::AWAITING_RETURN, self::IN_RETURN, self::RETURN_PAUSED,
            self::IN_PROGRESS,
        ], true);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::COMPLETED, self::DELIVERED, self::INVOICED], true);
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    /** Returns allowed transitions from this status */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::AWAITING_DISPATCH, self::IN_DISPLACEMENT, self::IN_PROGRESS, self::WAITING_APPROVAL, self::CANCELLED],
            self::AWAITING_DISPATCH => [self::IN_DISPLACEMENT, self::CANCELLED],
            self::IN_DISPLACEMENT => [self::DISPLACEMENT_PAUSED, self::AT_CLIENT, self::CANCELLED],
            self::DISPLACEMENT_PAUSED => [self::IN_DISPLACEMENT],
            self::AT_CLIENT => [self::IN_SERVICE, self::CANCELLED],
            self::IN_SERVICE => [self::SERVICE_PAUSED, self::WAITING_PARTS, self::AWAITING_RETURN, self::CANCELLED],
            self::SERVICE_PAUSED => [self::IN_SERVICE],
            self::AWAITING_RETURN => [self::IN_RETURN, self::COMPLETED],
            self::IN_RETURN => [self::RETURN_PAUSED, self::COMPLETED],
            self::RETURN_PAUSED => [self::IN_RETURN],
            self::WAITING_PARTS => [self::IN_SERVICE, self::CANCELLED],
            self::WAITING_APPROVAL => [self::OPEN, self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [self::WAITING_APPROVAL, self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [self::INVOICED],
            self::INVOICED => [],
            self::CANCELLED => [self::OPEN],
            self::IN_PROGRESS => [self::WAITING_PARTS, self::AWAITING_RETURN, self::COMPLETED, self::CANCELLED],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
