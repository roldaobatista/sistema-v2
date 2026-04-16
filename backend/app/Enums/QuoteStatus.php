<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case PENDING_INTERNAL_APPROVAL = 'pending_internal_approval';
    case INTERNALLY_APPROVED = 'internally_approved';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case IN_EXECUTION = 'in_execution';
    case INSTALLATION_TESTING = 'installation_testing';
    case RENEGOTIATION = 'renegotiation';
    case INVOICED = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::PENDING_INTERNAL_APPROVAL => 'Aguard. Aprovação Interna',
            self::INTERNALLY_APPROVED => 'Aprovado Internamente',
            self::SENT => 'Enviado',
            self::APPROVED => 'Aprovado',
            self::REJECTED => 'Rejeitado',
            self::EXPIRED => 'Expirado',
            self::IN_EXECUTION => 'Em Execução',
            self::INSTALLATION_TESTING => 'Instalação p/ Teste',
            self::RENEGOTIATION => 'Em Renegociação',
            self::INVOICED => 'Faturado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-surface-100 text-surface-700',
            self::PENDING_INTERNAL_APPROVAL => 'bg-amber-100 text-amber-700',
            self::INTERNALLY_APPROVED => 'bg-teal-100 text-teal-700',
            self::SENT => 'bg-blue-100 text-blue-700',
            self::APPROVED => 'bg-emerald-100 text-emerald-700',
            self::REJECTED => 'bg-red-100 text-red-700',
            self::EXPIRED => 'bg-amber-100 text-amber-700',
            self::IN_EXECUTION => 'bg-indigo-100 text-indigo-700',
            self::INSTALLATION_TESTING => 'bg-orange-100 text-orange-700',
            self::RENEGOTIATION => 'bg-rose-100 text-rose-700',
            self::INVOICED => 'bg-purple-100 text-purple-700',
        };
    }

    /** Statuses que permitem edição */
    public function isMutable(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_INTERNAL_APPROVAL, self::REJECTED, self::RENEGOTIATION], true);
    }

    /** Statuses que permitem conversão em OS/Chamado */
    public function isConvertible(): bool
    {
        return in_array($this, [self::APPROVED, self::INTERNALLY_APPROVED], true);
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_INTERNAL_APPROVAL, self::SENT, self::EXPIRED],
            self::PENDING_INTERNAL_APPROVAL => [self::INTERNALLY_APPROVED, self::REJECTED, self::DRAFT],
            self::INTERNALLY_APPROVED => [self::SENT, self::REJECTED],
            self::SENT => [self::APPROVED, self::REJECTED, self::EXPIRED, self::RENEGOTIATION],
            self::APPROVED => [self::IN_EXECUTION, self::INSTALLATION_TESTING, self::RENEGOTIATION],
            self::REJECTED => [self::DRAFT, self::RENEGOTIATION],
            self::EXPIRED => [self::DRAFT, self::RENEGOTIATION],
            self::IN_EXECUTION => [self::INVOICED, self::RENEGOTIATION],
            self::INSTALLATION_TESTING => [self::IN_EXECUTION, self::INVOICED, self::RENEGOTIATION],
            self::RENEGOTIATION => [self::DRAFT, self::SENT, self::APPROVED, self::REJECTED],
            self::INVOICED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
