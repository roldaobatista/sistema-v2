import { formatCurrency } from '@/lib/utils'

export const fmtBRL = formatCurrency

export const normalizeCommissionRole = (value: string | null | undefined) => {
    if (!value) return null

    const normalized = value.trim().toLowerCase()
    const aliases: Record<string, 'tecnico' | 'vendedor' | 'motorista'> = {
        tecnico: 'tecnico',
        technician: 'tecnico',
        vendedor: 'vendedor',
        seller: 'vendedor',
        salesperson: 'vendedor',
        motorista: 'motorista',
        driver: 'motorista',
    }

    return aliases[normalized] ?? null
}

export const getCommissionRoleLabel = (value: string | null | undefined) => {
    const canonical = normalizeCommissionRole(value)

    return canonical ? roleLabels[canonical] : (value ?? '-')
}

export const fmtDate = (value: string | null | undefined) => {
    if (!value) return '-'

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return '-'

    return date.toLocaleDateString('pt-BR')
}

export const roleLabels: Record<string, string> = {
    tecnico: 'Tecnico',
    vendedor: 'Vendedor',
    motorista: 'Motorista',
}

export const settlementStatusLabel = (status: string) => {
    const map: Record<string, string> = {
        open: 'Aberto',
        closed: 'Fechado',
        approved: 'Aprovado',
        rejected: 'Rejeitado',
        pending_approval: 'Aguard. aprovacao (legado)',
        paid: 'Pago',
    }

    return map[status] ?? status
}

export const settlementStatusVariant = (status: string) => {
    const map: Record<string, 'secondary' | 'default' | 'success' | 'danger' | 'warning'> = {
        open: 'secondary',
        closed: 'default',
        approved: 'success',
        rejected: 'danger',
        pending_approval: 'warning',
        paid: 'success',
    }

    return map[status] ?? 'secondary'
}

export const normalizeSettlementStatus = (status: string | null | undefined) => {
    if (!status) return 'open'

    const normalized = status.trim().toLowerCase()

    if (normalized === 'pending_approval') {
        return 'closed'
    }

    return normalized
}

export const normalizeCommissionDisputeStatus = (value: string | null | undefined) => {
    if (!value) return 'open'

    const normalized = value.trim().toLowerCase()

    if (normalized === 'resolved') {
        return 'resolved'
    }

    if (normalized === 'accepted' || normalized === 'rejected' || normalized === 'open') {
        return normalized
    }

    return normalized
}

export const getCommissionDisputeStatusLabel = (value: string | null | undefined) => {
    const normalized = normalizeCommissionDisputeStatus(value)

    const map: Record<string, string> = {
        open: 'Aberta',
        accepted: 'Aceita',
        rejected: 'Rejeitada',
        resolved: 'Resolvida (legado)',
    }

    return map[normalized] ?? (value ?? '-')
}

export const getCommissionDisputeStatusVariant = (value: string | null | undefined) => {
    const normalized = normalizeCommissionDisputeStatus(value)

    const map: Record<string, 'secondary' | 'success' | 'danger' | 'default'> = {
        open: 'secondary',
        accepted: 'success',
        rejected: 'danger',
        resolved: 'default',
    }

    return map[normalized] ?? 'secondary'
}
