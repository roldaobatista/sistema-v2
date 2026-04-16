import { QUOTE_STATUS } from '@/lib/constants';
import { FileText, Send, CheckCircle, XCircle, Clock, DollarSign, AlertCircle, ShieldCheck, Wrench, FlaskConical, MessageSquare } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { BadgeProps } from '@/components/ui/badge';

export const QUOTE_STATUS_CONFIG: Record<string, { label: string; variant: NonNullable<BadgeProps['variant']>; icon: LucideIcon }> = {
    [QUOTE_STATUS.DRAFT]: { label: 'Rascunho', variant: 'default', icon: FileText },
    [QUOTE_STATUS.PENDING_INTERNAL]: { label: 'Aguard. Aprovação Interna', variant: 'warning', icon: AlertCircle },
    [QUOTE_STATUS.INTERNALLY_APPROVED]: { label: 'Aprovado Internamente', variant: 'info', icon: ShieldCheck },
    [QUOTE_STATUS.SENT]: { label: 'Enviado', variant: 'info', icon: Send },
    [QUOTE_STATUS.APPROVED]: { label: 'Aprovado', variant: 'success', icon: CheckCircle },
    [QUOTE_STATUS.REJECTED]: { label: 'Rejeitado', variant: 'danger', icon: XCircle },
    [QUOTE_STATUS.EXPIRED]: { label: 'Expirado', variant: 'warning', icon: Clock },
    [QUOTE_STATUS.IN_EXECUTION]: { label: 'Em Execução', variant: 'info', icon: Wrench },
    [QUOTE_STATUS.INSTALLATION_TESTING]: { label: 'Instalação p/ Teste', variant: 'warning', icon: FlaskConical },
    [QUOTE_STATUS.RENEGOTIATION]: { label: 'Em Renegociação', variant: 'danger', icon: MessageSquare },
    [QUOTE_STATUS.INVOICED]: { label: 'Faturado', variant: 'info', icon: DollarSign },
};

export const MUTABLE_QUOTE_STATUSES = new Set<string>([
    QUOTE_STATUS.DRAFT,
    QUOTE_STATUS.PENDING_INTERNAL,
    QUOTE_STATUS.REJECTED,
    QUOTE_STATUS.RENEGOTIATION,
]);

export function isMutableQuoteStatus(status: string): boolean {
    return MUTABLE_QUOTE_STATUSES.has(status);
}
