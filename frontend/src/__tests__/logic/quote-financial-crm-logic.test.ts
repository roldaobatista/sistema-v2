import { describe, it, expect } from 'vitest'

// â”€â”€ Quote Status Machine (matching real backend) â”€â”€

type QuoteStatus =
  | 'draft' | 'pending_internal_approval' | 'internally_approved'
  | 'sent' | 'approved' | 'rejected' | 'expired'
  | 'in_execution' | 'installation_testing' | 'renegotiation' | 'invoiced'

const QUOTE_LABELS: Record<QuoteStatus, string> = {
  draft: 'Rascunho',
  pending_internal_approval: 'Aguard. Aprovação Interna',
  internally_approved: 'Aprovado Internamente',
  sent: 'Enviado',
  approved: 'Aprovado',
  rejected: 'Rejeitado',
  expired: 'Expirado',
  in_execution: 'Em Execução',
  installation_testing: 'Instalação p/ Teste',
  renegotiation: 'Em Renegociação',
  invoiced: 'Faturado',
}

function isQuoteMutable(status: QuoteStatus): boolean {
  return ['draft', 'pending_internal_approval', 'rejected', 'renegotiation'].includes(status)
}

function isQuoteConvertible(status: QuoteStatus): boolean {
  return ['approved', 'internally_approved'].includes(status)
}

function canSendToClient(status: QuoteStatus): boolean {
  return status === 'internally_approved'
}

function isQuoteExpired(status: QuoteStatus, validUntil: Date | null): boolean {
  if (!validUntil) return false
  const expirableStatuses: QuoteStatus[] = ['sent', 'pending_internal_approval', 'internally_approved']
  if (!expirableStatuses.includes(status)) return false
  return validUntil < new Date()
}

describe('Quote Status Labels', () => {
  it('draft → Rascunho', () => expect(QUOTE_LABELS.draft).toBe('Rascunho'))
  it('sent → Enviado', () => expect(QUOTE_LABELS.sent).toBe('Enviado'))
  it('approved → Aprovado', () => expect(QUOTE_LABELS.approved).toBe('Aprovado'))
  it('rejected → Rejeitado', () => expect(QUOTE_LABELS.rejected).toBe('Rejeitado'))
  it('invoiced → Faturado', () => expect(QUOTE_LABELS.invoiced).toBe('Faturado'))
  it('all 11 statuses have labels', () => {
    expect(Object.keys(QUOTE_LABELS)).toHaveLength(11)
  })
})

describe('Quote isMutable', () => {
  it('draft → mutable', () => expect(isQuoteMutable('draft')).toBe(true))
  it('pending_internal → mutable', () => expect(isQuoteMutable('pending_internal_approval')).toBe(true))
  it('rejected → mutable', () => expect(isQuoteMutable('rejected')).toBe(true))
  it('renegotiation → mutable', () => expect(isQuoteMutable('renegotiation')).toBe(true))
  it('sent → NOT mutable', () => expect(isQuoteMutable('sent')).toBe(false))
  it('approved → NOT mutable', () => expect(isQuoteMutable('approved')).toBe(false))
  it('invoiced → NOT mutable', () => expect(isQuoteMutable('invoiced')).toBe(false))
})

describe('Quote isConvertible', () => {
  it('approved → convertible', () => expect(isQuoteConvertible('approved')).toBe(true))
  it('internally_approved → convertible', () => expect(isQuoteConvertible('internally_approved')).toBe(true))
  it('draft → NOT convertible', () => expect(isQuoteConvertible('draft')).toBe(false))
  it('sent → NOT convertible', () => expect(isQuoteConvertible('sent')).toBe(false))
  it('invoiced → NOT convertible', () => expect(isQuoteConvertible('invoiced')).toBe(false))
})

describe('Quote canSendToClient', () => {
  it('internally_approved → can send', () => expect(canSendToClient('internally_approved')).toBe(true))
  it('draft → cannot send', () => expect(canSendToClient('draft')).toBe(false))
  it('sent → cannot send', () => expect(canSendToClient('sent')).toBe(false))
  it('approved → cannot send', () => expect(canSendToClient('approved')).toBe(false))
})

describe('Quote isExpired', () => {
  it('sent + past date → expired', () => {
    const pastDate = new Date()
    pastDate.setDate(pastDate.getDate() - 5)
    expect(isQuoteExpired('sent', pastDate)).toBe(true)
  })
  it('sent + future date → NOT expired', () => {
    const futureDate = new Date()
    futureDate.setDate(futureDate.getDate() + 30)
    expect(isQuoteExpired('sent', futureDate)).toBe(false)
  })
  it('approved + past date → NOT expired (not expirable)', () => {
    const pastDate = new Date()
    pastDate.setDate(pastDate.getDate() - 5)
    expect(isQuoteExpired('approved', pastDate)).toBe(false)
  })
  it('sent + null → NOT expired', () => {
    expect(isQuoteExpired('sent', null)).toBe(false)
  })
})

// â”€â”€ Financial Status (matching backend FinancialStatus enum) â”€â”€

type FinStatus = 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled' | 'renegotiated'

function isFinancialOpen(status: FinStatus): boolean {
  return ['pending', 'partial', 'overdue'].includes(status)
}

function isFinancialSettled(status: FinStatus): boolean {
  return ['paid', 'cancelled', 'renegotiated'].includes(status)
}

function recalculateARStatus(
  amount: number,
  amountPaid: number,
  dueDate: Date | null,
  currentStatus: FinStatus
): FinStatus {
  if (currentStatus === 'cancelled' || currentStatus === 'renegotiated') return currentStatus

  const remaining = amount - amountPaid
  if (remaining <= 0) return 'paid'
  if (dueDate && dueDate < new Date()) return 'overdue'
  if (amountPaid > 0) return 'partial'
  return 'pending'
}

const FIN_LABELS: Record<FinStatus, string> = {
  pending: 'Pendente', partial: 'Parcial', paid: 'Pago',
  overdue: 'Vencido', cancelled: 'Cancelado', renegotiated: 'Renegociado',
}

const FIN_COLORS: Record<FinStatus, string> = {
  pending: 'warning', partial: 'info', paid: 'success',
  overdue: 'danger', cancelled: 'default', renegotiated: 'teal',
}

describe('Financial isOpen', () => {
  it('pending → open', () => expect(isFinancialOpen('pending')).toBe(true))
  it('partial → open', () => expect(isFinancialOpen('partial')).toBe(true))
  it('overdue → open', () => expect(isFinancialOpen('overdue')).toBe(true))
  it('paid → NOT open', () => expect(isFinancialOpen('paid')).toBe(false))
  it('cancelled → NOT open', () => expect(isFinancialOpen('cancelled')).toBe(false))
})

describe('Financial isSettled', () => {
  it('paid → settled', () => expect(isFinancialSettled('paid')).toBe(true))
  it('cancelled → settled', () => expect(isFinancialSettled('cancelled')).toBe(true))
  it('renegotiated → settled', () => expect(isFinancialSettled('renegotiated')).toBe(true))
  it('pending → NOT settled', () => expect(isFinancialSettled('pending')).toBe(false))
  it('overdue → NOT settled', () => expect(isFinancialSettled('overdue')).toBe(false))
})

describe('Financial recalculateARStatus', () => {
  it('fully paid → paid', () => expect(recalculateARStatus(1000, 1000, null, 'pending')).toBe('paid'))
  it('overpaid → paid', () => expect(recalculateARStatus(500, 600, null, 'pending')).toBe('paid'))
  it('partial → partial', () => {
    const future = new Date()
    future.setDate(future.getDate() + 30)
    expect(recalculateARStatus(1000, 300, future, 'pending')).toBe('partial')
  })
  it('past due → overdue', () => {
    const past = new Date()
    past.setDate(past.getDate() - 5)
    expect(recalculateARStatus(1000, 0, past, 'pending')).toBe('overdue')
  })
  it('zero payment + future → pending', () => {
    const future = new Date()
    future.setDate(future.getDate() + 30)
    expect(recalculateARStatus(1000, 0, future, 'pending')).toBe('pending')
  })
  it('cancelled → stays cancelled', () => expect(recalculateARStatus(1000, 1000, null, 'cancelled')).toBe('cancelled'))
  it('renegotiated → stays renegotiated', () => expect(recalculateARStatus(1000, 0, null, 'renegotiated')).toBe('renegotiated'))
})

describe('Financial Labels', () => {
  it('all 6 statuses have labels', () => expect(Object.keys(FIN_LABELS)).toHaveLength(6))
  it('pending → Pendente', () => expect(FIN_LABELS.pending).toBe('Pendente'))
  it('paid → Pago', () => expect(FIN_LABELS.paid).toBe('Pago'))
  it('overdue → Vencido', () => expect(FIN_LABELS.overdue).toBe('Vencido'))
})

describe('Financial Colors', () => {
  it('pending → warning', () => expect(FIN_COLORS.pending).toBe('warning'))
  it('paid → success', () => expect(FIN_COLORS.paid).toBe('success'))
  it('overdue → danger', () => expect(FIN_COLORS.overdue).toBe('danger'))
  it('cancelled → default', () => expect(FIN_COLORS.cancelled).toBe('default'))
})

// â”€â”€ CRM Deal Status (matching backend CrmDeal model) â”€â”€

type DealStatus = 'open' | 'won' | 'lost'

function canMoveDealToStage(status: DealStatus): boolean {
  return status === 'open'
}

const DEAL_SOURCES = [
  'calibracao_vencendo', 'indicacao', 'prospeccao', 'chamado',
  'contrato_renovacao', 'retorno', 'outro',
]

describe('CRM Deal canMoveDealToStage', () => {
  it('open → can move', () => expect(canMoveDealToStage('open')).toBe(true))
  it('won → cannot move', () => expect(canMoveDealToStage('won')).toBe(false))
  it('lost → cannot move', () => expect(canMoveDealToStage('lost')).toBe(false))
})

describe('CRM Deal Sources', () => {
  it('has 7 sources', () => expect(DEAL_SOURCES).toHaveLength(7))
  it('includes calibracao_vencendo', () => expect(DEAL_SOURCES).toContain('calibracao_vencendo'))
  it('includes indicacao', () => expect(DEAL_SOURCES).toContain('indicacao'))
})
