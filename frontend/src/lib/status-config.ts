import {
    AlertCircle, Clock, Truck, ArrowRight, CheckCircle, XCircle,
    FileText, Pause, Package, ShieldCheck, Send, ThumbsUp, ThumbsDown,
    Timer, Ban, RotateCcw, Receipt, Hourglass, CircleDot, Play, MapPin,
    Undo2, Wrench, FlaskConical, MessageSquare,
} from 'lucide-react'
import type { ComponentType } from 'react'
import type { BadgeProps } from '@/components/ui/badge'

type BadgeVariant = NonNullable<BadgeProps['variant']>

interface StatusEntry {
    label: string
    variant: BadgeVariant
    icon: ComponentType<{ className?: string }>
}

export const workOrderStatus: Record<string, StatusEntry> = {
    open: { label: 'Aberta', variant: 'info', icon: AlertCircle },
    awaiting_dispatch: { label: 'Aguard. Despacho', variant: 'warning', icon: Hourglass },
    in_displacement: { label: 'Em Deslocamento', variant: 'info', icon: Truck },
    displacement_paused: { label: 'Desloc. Pausado', variant: 'warning', icon: Pause },
    at_client: { label: 'No Cliente', variant: 'info', icon: MapPin },
    in_service: { label: 'Em Servico', variant: 'warning', icon: Play },
    service_paused: { label: 'Servico Pausado', variant: 'warning', icon: Pause },
    awaiting_return: { label: 'Servico Concluido', variant: 'info', icon: CheckCircle },
    in_return: { label: 'Em Retorno', variant: 'info', icon: Undo2 },
    return_paused: { label: 'Retorno Pausado', variant: 'warning', icon: Pause },
    in_progress: { label: 'Em Andamento', variant: 'warning', icon: Play },
    waiting_parts: { label: 'Aguard. Pecas', variant: 'warning', icon: Package },
    waiting_approval: { label: 'Aguard. Aprov.', variant: 'warning', icon: Hourglass },
    completed: { label: 'Finalizada', variant: 'success', icon: CheckCircle },
    delivered: { label: 'Entregue', variant: 'success', icon: ShieldCheck },
    invoiced: { label: 'Faturada', variant: 'brand', icon: Receipt },
    cancelled: { label: 'Cancelada', variant: 'danger', icon: XCircle },
}

export const serviceCallStatus: Record<string, StatusEntry> = {
    pending_scheduling: { label: 'Pendente Agendamento', variant: 'info', icon: AlertCircle },
    scheduled: { label: 'Agendado', variant: 'warning', icon: Clock },
    rescheduled: { label: 'Reagendado', variant: 'warning', icon: ArrowRight },
    awaiting_confirmation: { label: 'Aguard. Confirmacao', variant: 'info', icon: Hourglass },
    converted_to_os: { label: 'Convertido em OS', variant: 'success', icon: CheckCircle },
    cancelled: { label: 'Cancelado', variant: 'danger', icon: XCircle },
}

export const financialStatus: Record<string, StatusEntry> = {
    pending: { label: 'Pendente', variant: 'warning', icon: Clock },
    partial: { label: 'Parcial', variant: 'info', icon: Pause },
    paid: { label: 'Pago', variant: 'success', icon: CheckCircle },
    overdue: { label: 'Vencido', variant: 'danger', icon: AlertCircle },
    cancelled: { label: 'Cancelado', variant: 'default', icon: XCircle },
}

export const quoteStatus: Record<string, StatusEntry> = {
    draft: { label: 'Rascunho', variant: 'default', icon: FileText },
    pending_internal_approval: { label: 'Aprov. Interna', variant: 'warning', icon: Hourglass },
    internally_approved: { label: 'Aprovado Int.', variant: 'info', icon: ThumbsUp },
    sent: { label: 'Enviado', variant: 'info', icon: Send },
    approved: { label: 'Aprovado', variant: 'success', icon: ThumbsUp },
    rejected: { label: 'Rejeitado', variant: 'danger', icon: ThumbsDown },
    expired: { label: 'Expirado', variant: 'default', icon: Timer },
    in_execution: { label: 'Em Execucao', variant: 'info', icon: Wrench },
    installation_testing: { label: 'Instal. Teste', variant: 'warning', icon: FlaskConical },
    renegotiation: { label: 'Renegociacao', variant: 'danger', icon: MessageSquare },
    invoiced: { label: 'Faturado', variant: 'brand', icon: Receipt },
}

export const commissionStatus: Record<string, StatusEntry> = {
    pending: { label: 'Pendente', variant: 'warning', icon: Clock },
    approved: { label: 'Aprovada', variant: 'info', icon: ThumbsUp },
    paid: { label: 'Paga', variant: 'success', icon: CheckCircle },
    reversed: { label: 'Estornada', variant: 'danger', icon: RotateCcw },
    rejected: { label: 'Rejeitada', variant: 'danger', icon: ThumbsDown },
    open: { label: 'Aberta', variant: 'info', icon: CircleDot },
    accepted: { label: 'Aceita', variant: 'success', icon: ThumbsUp },
    closed: { label: 'Fechada', variant: 'default', icon: Ban },
}

export const expenseStatus: Record<string, StatusEntry> = {
    pending: { label: 'Pendente', variant: 'warning', icon: Clock },
    reviewed: { label: 'Revisada', variant: 'info', icon: FileText },
    approved: { label: 'Aprovada', variant: 'success', icon: ThumbsUp },
    rejected: { label: 'Rejeitada', variant: 'danger', icon: ThumbsDown },
    reimbursed: { label: 'Reembolsada', variant: 'success', icon: CheckCircle },
}

export const equipmentStatus: Record<string, StatusEntry> = {
    active: { label: 'Ativo', variant: 'success', icon: CheckCircle },
    in_calibration: { label: 'Em Calibracao', variant: 'warning', icon: Clock },
    in_maintenance: { label: 'Em Manutencao', variant: 'warning', icon: Package },
    out_of_service: { label: 'Fora de Uso', variant: 'danger', icon: XCircle },
    discarded: { label: 'Descartado', variant: 'default', icon: Ban },
}

export const priorityConfig: Record<string, { label: string; variant: BadgeVariant }> = {
    low: { label: 'Baixa', variant: 'default' },
    normal: { label: 'Normal', variant: 'info' },
    high: { label: 'Alta', variant: 'warning' },
    urgent: { label: 'Urgente', variant: 'danger' },
}

export function getStatusEntry(
    map: Record<string, StatusEntry>,
    status: string
): StatusEntry {
    return map[status] ?? { label: status, variant: 'default', icon: CircleDot }
}
