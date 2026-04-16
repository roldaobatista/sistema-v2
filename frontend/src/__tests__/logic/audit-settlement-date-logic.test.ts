import { describe, it, expect } from 'vitest'

// ── Audit Log Frontend Display Helpers ──

interface AuditEntry {
  id: number
  action: 'created' | 'updated' | 'deleted' | 'restored'
  auditable_type: string
  user_name: string
  created_at: string
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
}

function getAuditActionLabel(action: string): string {
  const labels: Record<string, string> = {
    created: 'Criou',
    updated: 'Atualizou',
    deleted: 'Excluiu',
    restored: 'Restaurou',
  }
  return labels[action] || action
}

function getAuditActionColor(action: string): string {
  const colors: Record<string, string> = {
    created: 'green',
    updated: 'blue',
    deleted: 'red',
    restored: 'orange',
  }
  return colors[action] || 'gray'
}

function getModelName(auditableType: string): string {
  const parts = auditableType.split('\\')
  const className = parts[parts.length - 1]
  const map: Record<string, string> = {
    Customer: 'Cliente',
    WorkOrder: 'Ordem de Serviço',
    Equipment: 'Equipamento',
    Quote: 'Orçamento',
    Invoice: 'Fatura',
    Supplier: 'Fornecedor',
    AccountReceivable: 'Conta a Receber',
    AccountPayable: 'Conta a Pagar',
    AgendaItem: 'Item de Agenda',
    CrmDeal: 'Negócio CRM',
  }
  return map[className] || className
}

function getChangedFields(entry: AuditEntry): string[] {
  if (!entry.new_values) return []
  return Object.keys(entry.new_values)
}

describe('Audit Action Label', () => {
  it('created → Criou', () => expect(getAuditActionLabel('created')).toBe('Criou'))
  it('updated → Atualizou', () => expect(getAuditActionLabel('updated')).toBe('Atualizou'))
  it('deleted → Excluiu', () => expect(getAuditActionLabel('deleted')).toBe('Excluiu'))
  it('restored → Restaurou', () => expect(getAuditActionLabel('restored')).toBe('Restaurou'))
  it('unknown → raw', () => expect(getAuditActionLabel('xyz')).toBe('xyz'))
})

describe('Audit Action Color', () => {
  it('created → green', () => expect(getAuditActionColor('created')).toBe('green'))
  it('deleted → red', () => expect(getAuditActionColor('deleted')).toBe('red'))
  it('unknown → gray', () => expect(getAuditActionColor('unknown')).toBe('gray'))
})

describe('Model Name', () => {
  it('App\\Models\\Customer → Cliente', () => expect(getModelName('App\\Models\\Customer')).toBe('Cliente'))
  it('App\\Models\\WorkOrder → Ordem de Serviço', () => expect(getModelName('App\\Models\\WorkOrder')).toBe('Ordem de Serviço'))
  it('App\\Models\\Equipment → Equipamento', () => expect(getModelName('App\\Models\\Equipment')).toBe('Equipamento'))
  it('unknown class → raw name', () => expect(getModelName('App\\Models\\FooBar')).toBe('FooBar'))
})

describe('Changed Fields', () => {
  it('returns field names', () => {
    const entry: AuditEntry = {
      id: 1, action: 'updated', auditable_type: 'Customer',
      user_name: 'Admin', created_at: '2026-03-15',
      old_values: { name: 'Old' }, new_values: { name: 'New', email: 'new@test.com' },
    }
    expect(getChangedFields(entry)).toEqual(['name', 'email'])
  })
  it('null new_values → empty', () => {
    const entry: AuditEntry = {
      id: 1, action: 'deleted', auditable_type: 'Customer',
      user_name: 'Admin', created_at: '2026-03-15',
      old_values: null, new_values: null,
    }
    expect(getChangedFields(entry)).toEqual([])
  })
})

// ── Commission Settlement Display ──

interface Settlement {
  id: number
  userName: string
  totalAmount: number
  eventCount: number
  period: string
  status: 'pending' | 'approved' | 'paid' | 'contested'
}

function getSettlementStatusLabel(status: string): string {
  const map: Record<string, string> = {
    pending: 'Pendente',
    approved: 'Aprovado',
    paid: 'Pago',
    contested: 'Contestado',
  }
  return map[status] || status
}

function calculateSettlementSummary(settlements: Settlement[]) {
  const total = settlements.reduce((sum, s) => sum + s.totalAmount, 0)
  const pending = settlements.filter(s => s.status === 'pending')
  const approved = settlements.filter(s => s.status === 'approved')
  const paid = settlements.filter(s => s.status === 'paid')
  return {
    total: Math.round(total * 100) / 100,
    pendingCount: pending.length,
    approvedCount: approved.length,
    paidCount: paid.length,
    pendingTotal: Math.round(pending.reduce((s, p) => s + p.totalAmount, 0) * 100) / 100,
  }
}

describe('Settlement Status Label', () => {
  it('pending → Pendente', () => expect(getSettlementStatusLabel('pending')).toBe('Pendente'))
  it('approved → Aprovado', () => expect(getSettlementStatusLabel('approved')).toBe('Aprovado'))
  it('paid → Pago', () => expect(getSettlementStatusLabel('paid')).toBe('Pago'))
  it('contested → Contestado', () => expect(getSettlementStatusLabel('contested')).toBe('Contestado'))
})

describe('Settlement Summary', () => {
  const settlements: Settlement[] = [
    { id: 1, userName: 'João', totalAmount: 2000, eventCount: 5, period: '2026-03', status: 'pending' },
    { id: 2, userName: 'Maria', totalAmount: 3500, eventCount: 8, period: '2026-03', status: 'approved' },
    { id: 3, userName: 'Pedro', totalAmount: 1500, eventCount: 3, period: '2026-03', status: 'paid' },
    { id: 4, userName: 'Ana', totalAmount: 800, eventCount: 2, period: '2026-03', status: 'pending' },
  ]

  it('total amount', () => {
    const summary = calculateSettlementSummary(settlements)
    expect(summary.total).toBe(7800)
  })
  it('pending count', () => expect(calculateSettlementSummary(settlements).pendingCount).toBe(2))
  it('approved count', () => expect(calculateSettlementSummary(settlements).approvedCount).toBe(1))
  it('paid count', () => expect(calculateSettlementSummary(settlements).paidCount).toBe(1))
  it('pending total', () => expect(calculateSettlementSummary(settlements).pendingTotal).toBe(2800))
})

// ── Date Range utilities ──

function formatDateBR(date: Date): string {
  const d = date.getDate().toString().padStart(2, '0')
  const m = (date.getMonth() + 1).toString().padStart(2, '0')
  return `${d}/${m}/${date.getFullYear()}`
}

function parseDateBR(str: string): Date | null {
  const match = str.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
  if (!match) return null
  return new Date(+match[3], +match[2] - 1, +match[1])
}

function daysBetween(from: Date, to: Date): number {
  return Math.round((to.getTime() - from.getTime()) / (1000 * 60 * 60 * 24))
}

describe('Date BR Format', () => {
  it('formats date', () => expect(formatDateBR(new Date(2026, 2, 15))).toBe('15/03/2026'))
  it('single digit padding', () => expect(formatDateBR(new Date(2026, 0, 5))).toBe('05/01/2026'))
})

describe('Parse Date BR', () => {
  it('valid date', () => {
    const d = parseDateBR('15/03/2026')!
    expect(d.getDate()).toBe(15)
    expect(d.getMonth()).toBe(2) // March
    expect(d.getFullYear()).toBe(2026)
  })
  it('invalid → null', () => expect(parseDateBR('invalid')).toBeNull())
  it('incomplete → null', () => expect(parseDateBR('15/03')).toBeNull())
})

describe('Days Between', () => {
  it('same day → 0', () => expect(daysBetween(new Date(2026, 0, 1), new Date(2026, 0, 1))).toBe(0))
  it('30 days', () => expect(daysBetween(new Date(2026, 0, 1), new Date(2026, 0, 31))).toBe(30))
  it('365 days', () => expect(daysBetween(new Date(2026, 0, 1), new Date(2027, 0, 1))).toBe(365))
})
