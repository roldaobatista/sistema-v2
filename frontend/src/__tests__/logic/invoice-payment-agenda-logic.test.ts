import { describe, it, expect } from 'vitest'

// ── Invoice Number Generator (matching backend Invoice::nextNumber) ──

function nextInvoiceNumber(lastNumber: string | null): string {
  if (!lastNumber) return 'NF-000001'
  const numeric = parseInt(lastNumber.replace('NF-', ''), 10)
  const next = numeric + 1
  return `NF-${String(next).padStart(6, '0')}`
}

describe('Invoice Number Generator', () => {
  it('first invoice → NF-000001', () => expect(nextInvoiceNumber(null)).toBe('NF-000001'))
  it('NF-000005 → NF-000006', () => expect(nextInvoiceNumber('NF-000005')).toBe('NF-000006'))
  it('NF-000099 → NF-000100', () => expect(nextInvoiceNumber('NF-000099')).toBe('NF-000100'))
  it('NF-999999 → NF-1000000', () => expect(nextInvoiceNumber('NF-999999')).toBe('NF-1000000'))
})

// ── Payment Amount Calculations ──

function calculateRemainingAmount(totalAmount: number, totalPaid: number): number {
  return Math.max(0, Math.round((totalAmount - totalPaid) * 100) / 100)
}

function calculatePaymentPercentage(totalAmount: number, totalPaid: number): number {
  if (totalAmount <= 0) return 0
  return Math.min(100, Math.round((totalPaid / totalAmount) * 1000) / 10)
}

function determinePaymentStatus(
  amount: number,
  amountPaid: number,
  dueDate: Date | null
): 'pending' | 'partial' | 'paid' | 'overdue' {
  const remaining = amount - amountPaid
  if (remaining <= 0) return 'paid'
  if (dueDate && dueDate < new Date()) return 'overdue'
  if (amountPaid > 0) return 'partial'
  return 'pending'
}

describe('Payment Remaining', () => {
  it('1000 - 500 = 500', () => expect(calculateRemainingAmount(1000, 500)).toBe(500))
  it('1000 - 1000 = 0', () => expect(calculateRemainingAmount(1000, 1000)).toBe(0))
  it('1000 - 1500 = 0 (capped)', () => expect(calculateRemainingAmount(1000, 1500)).toBe(0))
  it('0 - 0 = 0', () => expect(calculateRemainingAmount(0, 0)).toBe(0))
  it('precision test', () => expect(calculateRemainingAmount(999.99, 499.99)).toBe(500))
})

describe('Payment Percentage', () => {
  it('50%', () => expect(calculatePaymentPercentage(1000, 500)).toBe(50))
  it('100%', () => expect(calculatePaymentPercentage(1000, 1000)).toBe(100))
  it('0%', () => expect(calculatePaymentPercentage(1000, 0)).toBe(0))
  it('capped at 100%', () => expect(calculatePaymentPercentage(1000, 1500)).toBe(100))
  it('0 total → 0%', () => expect(calculatePaymentPercentage(0, 0)).toBe(0))
})

describe('Payment Status determination', () => {
  it('fully paid', () => expect(determinePaymentStatus(1000, 1000, null)).toBe('paid'))
  it('overpaid → paid', () => expect(determinePaymentStatus(1000, 1500, null)).toBe('paid'))
  it('partial payment', () => {
    const future = new Date(); future.setDate(future.getDate() + 30)
    expect(determinePaymentStatus(1000, 300, future)).toBe('partial')
  })
  it('overdue', () => {
    const past = new Date(); past.setDate(past.getDate() - 5)
    expect(determinePaymentStatus(1000, 0, past)).toBe('overdue')
  })
  it('pending', () => {
    const future = new Date(); future.setDate(future.getDate() + 30)
    expect(determinePaymentStatus(1000, 0, future)).toBe('pending')
  })
})

// ── Expense Categories (matching backend) ──

const EXPENSE_CATEGORIES: Record<string, string> = {
  pecas: 'Peças e Componentes',
  deslocamento: 'Deslocamento',
  alimentacao: 'Alimentação',
  hospedagem: 'Hospedagem',
  material_escritorio: 'Material de Escritório',
  aluguel: 'Aluguel',
  energia: 'Energia Elétrica',
  telecomunicacoes: 'Telecomunicações',
  outros: 'Outros',
}

function calculateKmExpense(kmQuantity: number, kmRate: number): number {
  return Math.round(kmQuantity * kmRate * 100) / 100
}

describe('Expense Categories', () => {
  it('has at least 9 categories', () => expect(Object.keys(EXPENSE_CATEGORIES).length).toBeGreaterThanOrEqual(9))
  it('includes pecas', () => expect(EXPENSE_CATEGORIES.pecas).toBe('Peças e Componentes'))
  it('includes deslocamento', () => expect(EXPENSE_CATEGORIES.deslocamento).toBe('Deslocamento'))
})

describe('KM Expense Calc', () => {
  it('100km × R$1.50 = R$150.00', () => expect(calculateKmExpense(100, 1.50)).toBe(150))
  it('250km × R$0.75 = R$187.50', () => expect(calculateKmExpense(250, 0.75)).toBe(187.5))
  it('0km = R$0', () => expect(calculateKmExpense(0, 1.50)).toBe(0))
})

// ── Agenda Status Machine (matching backend AgendaItemStatus) ──

type AgendaStatus = 'aberto' | 'em_andamento' | 'concluido' | 'cancelado' | 'aguardando' | 'pausado'

const AGENDA_LABELS: Record<AgendaStatus, string> = {
  aberto: 'Aberto',
  em_andamento: 'Em Andamento',
  concluido: 'Concluído',
  cancelado: 'Cancelado',
  aguardando: 'Aguardando',
  pausado: 'Pausado',
}

function canTransitionAgenda(from: AgendaStatus, to: AgendaStatus): boolean {
  const transitions: Record<AgendaStatus, AgendaStatus[]> = {
    aberto: ['em_andamento', 'cancelado', 'aguardando'],
    em_andamento: ['concluido', 'pausado', 'cancelado', 'aguardando'],
    aguardando: ['em_andamento', 'cancelado'],
    pausado: ['em_andamento', 'cancelado'],
    concluido: [],
    cancelado: [],
  }
  return transitions[from]?.includes(to) ?? false
}

function isAgendaTerminal(status: AgendaStatus): boolean {
  return status === 'concluido' || status === 'cancelado'
}

describe('Agenda Status Labels', () => {
  it('all 6 statuses', () => expect(Object.keys(AGENDA_LABELS)).toHaveLength(6))
  it('aberto → Aberto', () => expect(AGENDA_LABELS.aberto).toBe('Aberto'))
  it('concluido → Concluído', () => expect(AGENDA_LABELS.concluido).toBe('Concluído'))
})

describe('Agenda Transitions', () => {
  it('aberto → em_andamento', () => expect(canTransitionAgenda('aberto', 'em_andamento')).toBe(true))
  it('aberto → cancelado', () => expect(canTransitionAgenda('aberto', 'cancelado')).toBe(true))
  it('em_andamento → concluido', () => expect(canTransitionAgenda('em_andamento', 'concluido')).toBe(true))
  it('concluido → X (terminal)', () => expect(canTransitionAgenda('concluido', 'aberto')).toBe(false))
  it('cancelado → X (terminal)', () => expect(canTransitionAgenda('cancelado', 'em_andamento')).toBe(false))
  it('pausado → em_andamento', () => expect(canTransitionAgenda('pausado', 'em_andamento')).toBe(true))
  it('em_andamento → pausado', () => expect(canTransitionAgenda('em_andamento', 'pausado')).toBe(true))
})

describe('Agenda Terminal', () => {
  it('concluido is terminal', () => expect(isAgendaTerminal('concluido')).toBe(true))
  it('cancelado is terminal', () => expect(isAgendaTerminal('cancelado')).toBe(true))
  it('aberto is NOT terminal', () => expect(isAgendaTerminal('aberto')).toBe(false))
  it('em_andamento is NOT terminal', () => expect(isAgendaTerminal('em_andamento')).toBe(false))
})

// ── Tenant Display Name (matching backend accessor) ──

function getDisplayName(name: string, tradeName: string | null): string {
  return tradeName || name
}

function formatFullAddress(parts: {
  street?: string | null
  number?: string | null
  complement?: string | null
  neighborhood?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
}): string | null {
  const pieces: string[] = []
  if (parts.street) pieces.push(parts.street)
  if (parts.number) pieces.push(`nº ${parts.number}`)
  if (parts.complement) pieces.push(parts.complement)
  if (parts.neighborhood) pieces.push(parts.neighborhood)
  if (parts.city && parts.state) pieces.push(`${parts.city}/${parts.state}`)
  if (pieces.length === 0) return null
  let addr = pieces.join(', ')
  if (parts.zip) addr += ` — CEP ${parts.zip}`
  return addr
}

describe('Tenant Display Name', () => {
  it('returns trade_name when present', () => expect(getDisplayName('Razão', 'Fantasia')).toBe('Fantasia'))
  it('falls back to name', () => expect(getDisplayName('Razão', null)).toBe('Razão'))
  it('empty trade_name falls back', () => expect(getDisplayName('Razão', '')).toBe('Razão'))
})

describe('Full Address Formatter', () => {
  it('complete address', () => {
    const addr = formatFullAddress({
      street: 'Rua X', number: '123', complement: 'Sala 1',
      neighborhood: 'Centro', city: 'SP', state: 'SP', zip: '01310-100',
    })!
    expect(addr).toContain('Rua X')
    expect(addr).toContain('nº 123')
    expect(addr).toContain('SP/SP')
    expect(addr).toContain('CEP 01310-100')
  })
  it('null for empty', () => expect(formatFullAddress({})).toBeNull())
  it('no zip', () => {
    const addr = formatFullAddress({ street: 'Rua Y' })!
    expect(addr).not.toContain('CEP')
  })
})
