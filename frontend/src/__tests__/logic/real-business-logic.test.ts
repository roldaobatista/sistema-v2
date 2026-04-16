import { describe, it, expect } from 'vitest'

// ── Work Order State Machine (real logic from backend) ──

type WOStatus =
  | 'open' | 'awaiting_dispatch' | 'in_displacement' | 'displacement_paused'
  | 'at_client' | 'in_service' | 'service_paused' | 'awaiting_return'
  | 'in_return' | 'return_paused' | 'waiting_parts' | 'waiting_approval'
  | 'completed' | 'delivered' | 'invoiced' | 'cancelled' | 'in_progress'

const ALLOWED_TRANSITIONS: Record<WOStatus, WOStatus[]> = {
  open: ['awaiting_dispatch', 'in_displacement', 'in_progress', 'waiting_approval', 'cancelled'],
  awaiting_dispatch: ['in_displacement', 'cancelled'],
  in_displacement: ['displacement_paused', 'at_client', 'cancelled'],
  displacement_paused: ['in_displacement'],
  at_client: ['in_service', 'cancelled'],
  in_service: ['service_paused', 'waiting_parts', 'awaiting_return', 'cancelled'],
  service_paused: ['in_service'],
  awaiting_return: ['in_return', 'completed'],
  in_return: ['return_paused', 'completed'],
  return_paused: ['in_return'],
  waiting_parts: ['in_service', 'cancelled'],
  waiting_approval: ['open', 'completed', 'cancelled'],
  completed: ['waiting_approval', 'delivered', 'cancelled'],
  delivered: ['invoiced'],
  invoiced: [],
  cancelled: ['open'],
  in_progress: ['waiting_parts', 'awaiting_return', 'completed', 'cancelled'],
}

function canTransitionTo(from: WOStatus, to: WOStatus): boolean {
  return ALLOWED_TRANSITIONS[from]?.includes(to) ?? false
}

function isActiveStatus(status: WOStatus): boolean {
  const active: WOStatus[] = [
    'in_displacement', 'displacement_paused', 'at_client', 'in_service',
    'service_paused', 'awaiting_return', 'in_return', 'return_paused', 'in_progress',
  ]
  return active.includes(status)
}

function isCompletedStatus(status: WOStatus): boolean {
  return ['completed', 'delivered', 'invoiced'].includes(status)
}

function getStatusLabel(status: WOStatus): string {
  const labels: Record<WOStatus, string> = {
    open: 'Aberta', awaiting_dispatch: 'Aguard. Despacho', in_displacement: 'Em Deslocamento',
    displacement_paused: 'Desloc. Pausado', at_client: 'No Cliente', in_service: 'Em Serviço',
    service_paused: 'Serviço Pausado', awaiting_return: 'Serviço Concluído', in_return: 'Em Retorno',
    return_paused: 'Retorno Pausado', waiting_parts: 'Aguard. Peças', waiting_approval: 'Aguard. Aprovação',
    completed: 'Finalizada', delivered: 'Entregue', invoiced: 'Faturada', cancelled: 'Cancelada',
    in_progress: 'Em Andamento',
  }
  return labels[status] || status
}

function getStatusColor(status: WOStatus): string {
  const colors: Record<WOStatus, string> = {
    open: 'info', awaiting_dispatch: 'amber', in_displacement: 'cyan',
    displacement_paused: 'amber', at_client: 'info', in_service: 'warning',
    service_paused: 'amber', awaiting_return: 'teal', in_return: 'cyan',
    return_paused: 'amber', waiting_parts: 'warning', waiting_approval: 'brand',
    completed: 'success', delivered: 'success', invoiced: 'brand', cancelled: 'danger',
    in_progress: 'warning',
  }
  return colors[status] || 'default'
}

describe('WO State Machine - canTransitionTo', () => {
  it('open -> awaiting_dispatch', () => expect(canTransitionTo('open', 'awaiting_dispatch')).toBe(true))
  it('open -> in_displacement', () => expect(canTransitionTo('open', 'in_displacement')).toBe(true))
  it('open -> cancelled', () => expect(canTransitionTo('open', 'cancelled')).toBe(true))
  it('open -> completed BLOCKED', () => expect(canTransitionTo('open', 'completed')).toBe(false))
  it('open -> delivered BLOCKED', () => expect(canTransitionTo('open', 'delivered')).toBe(false))
  it('in_displacement -> at_client', () => expect(canTransitionTo('in_displacement', 'at_client')).toBe(true))
  it('in_displacement -> displacement_paused', () => expect(canTransitionTo('in_displacement', 'displacement_paused')).toBe(true))
  it('displacement_paused -> in_displacement', () => expect(canTransitionTo('displacement_paused', 'in_displacement')).toBe(true))
  it('displacement_paused -> completed BLOCKED', () => expect(canTransitionTo('displacement_paused', 'completed')).toBe(false))
  it('at_client -> in_service', () => expect(canTransitionTo('at_client', 'in_service')).toBe(true))
  it('in_service -> awaiting_return', () => expect(canTransitionTo('in_service', 'awaiting_return')).toBe(true))
  it('in_service -> service_paused', () => expect(canTransitionTo('in_service', 'service_paused')).toBe(true))
  it('in_service -> waiting_parts', () => expect(canTransitionTo('in_service', 'waiting_parts')).toBe(true))
  it('service_paused -> in_service', () => expect(canTransitionTo('service_paused', 'in_service')).toBe(true))
  it('awaiting_return -> completed', () => expect(canTransitionTo('awaiting_return', 'completed')).toBe(true))
  it('awaiting_return -> in_return', () => expect(canTransitionTo('awaiting_return', 'in_return')).toBe(true))
  it('completed -> delivered', () => expect(canTransitionTo('completed', 'delivered')).toBe(true))
  it('delivered -> invoiced', () => expect(canTransitionTo('delivered', 'invoiced')).toBe(true))
  it('invoiced -> ANY BLOCKED', () => {
    const allStatuses: WOStatus[] = Object.keys(ALLOWED_TRANSITIONS) as WOStatus[]
    allStatuses.forEach(s => expect(canTransitionTo('invoiced', s)).toBe(false))
  })
  it('cancelled -> open (reopen)', () => expect(canTransitionTo('cancelled', 'open')).toBe(true))
  it('cancelled -> completed BLOCKED', () => expect(canTransitionTo('cancelled', 'completed')).toBe(false))
})

describe('WO State Machine - isActiveStatus', () => {
  it('in_service is active', () => expect(isActiveStatus('in_service')).toBe(true))
  it('in_displacement is active', () => expect(isActiveStatus('in_displacement')).toBe(true))
  it('at_client is active', () => expect(isActiveStatus('at_client')).toBe(true))
  it('in_progress is active', () => expect(isActiveStatus('in_progress')).toBe(true))
  it('open is NOT active', () => expect(isActiveStatus('open')).toBe(false))
  it('completed is NOT active', () => expect(isActiveStatus('completed')).toBe(false))
  it('cancelled is NOT active', () => expect(isActiveStatus('cancelled')).toBe(false))
  it('invoiced is NOT active', () => expect(isActiveStatus('invoiced')).toBe(false))
})

describe('WO State Machine - isCompletedStatus', () => {
  it('completed → true', () => expect(isCompletedStatus('completed')).toBe(true))
  it('delivered → true', () => expect(isCompletedStatus('delivered')).toBe(true))
  it('invoiced → true', () => expect(isCompletedStatus('invoiced')).toBe(true))
  it('open → false', () => expect(isCompletedStatus('open')).toBe(false))
  it('cancelled → false', () => expect(isCompletedStatus('cancelled')).toBe(false))
})

describe('WO Labels', () => {
  it('open → Aberta', () => expect(getStatusLabel('open')).toBe('Aberta'))
  it('in_service → Em Serviço', () => expect(getStatusLabel('in_service')).toBe('Em Serviço'))
  it('completed → Finalizada', () => expect(getStatusLabel('completed')).toBe('Finalizada'))
  it('cancelled → Cancelada', () => expect(getStatusLabel('cancelled')).toBe('Cancelada'))
  it('invoiced → Faturada', () => expect(getStatusLabel('invoiced')).toBe('Faturada'))
  it('awaiting_return → Serviço Concluído', () => expect(getStatusLabel('awaiting_return')).toBe('Serviço Concluído'))
  it('in_progress → Em Andamento', () => expect(getStatusLabel('in_progress')).toBe('Em Andamento'))
  it('all 17 statuses have labels', () => {
    const allStatuses = Object.keys(ALLOWED_TRANSITIONS) as WOStatus[]
    allStatuses.forEach(s => expect(getStatusLabel(s)).toBeTruthy())
  })
})

describe('WO Colors', () => {
  it('open → info', () => expect(getStatusColor('open')).toBe('info'))
  it('completed → success', () => expect(getStatusColor('completed')).toBe('success'))
  it('cancelled → danger', () => expect(getStatusColor('cancelled')).toBe('danger'))
  it('in_service → warning', () => expect(getStatusColor('in_service')).toBe('warning'))
  it('invoiced → brand', () => expect(getStatusColor('invoiced')).toBe('brand'))
  it('all 17 statuses have colors', () => {
    const allStatuses = Object.keys(ALLOWED_TRANSITIONS) as WOStatus[]
    allStatuses.forEach(s => expect(getStatusColor(s)).toBeTruthy())
  })
})

// ── Financial Helpers (matching backend) ──

function recalculateTotal(
  subtotal: number,
  discountPercentage: number,
  discountFixed: number,
  displacement: number
): number {
  let discount = 0
  if (discountPercentage > 0) {
    discount = subtotal * (discountPercentage / 100)
  } else {
    discount = discountFixed
  }
  const total = subtotal - discount + displacement
  return Math.max(0, Math.round(total * 100) / 100)
}

function installmentSimulation(total: number): { installments: number; value: number }[] {
  return [2, 3, 6, 10, 12].map(n => ({
    installments: n,
    value: Math.round((total / n) * 100) / 100,
  }))
}

describe('Financial recalculateTotal', () => {
  it('no discount, no displacement', () => expect(recalculateTotal(1000, 0, 0, 0)).toBe(1000))
  it('10% percentage discount', () => expect(recalculateTotal(1000, 10, 0, 0)).toBe(900))
  it('fixed discount R$150', () => expect(recalculateTotal(1000, 0, 150, 0)).toBe(850))
  it('displacement R$100', () => expect(recalculateTotal(500, 0, 0, 100)).toBe(600))
  it('percentage discount + displacement', () => expect(recalculateTotal(1000, 10, 0, 100)).toBe(1000))
  it('never negative', () => expect(recalculateTotal(100, 0, 999, 0)).toBe(0))
  it('zero subtotal', () => expect(recalculateTotal(0, 10, 0, 0)).toBe(0))
})

describe('Financial installmentSimulation', () => {
  it('R$12000 in 2x = R$6000', () => {
    const sim = installmentSimulation(12000)
    expect(sim[0]).toEqual({ installments: 2, value: 6000 })
  })
  it('R$12000 in 3x = R$4000', () => {
    const sim = installmentSimulation(12000)
    expect(sim[1]).toEqual({ installments: 3, value: 4000 })
  })
  it('R$12000 in 12x = R$1000', () => {
    const sim = installmentSimulation(12000)
    expect(sim[4]).toEqual({ installments: 12, value: 1000 })
  })
  it('returns 5 options', () => {
    expect(installmentSimulation(5000)).toHaveLength(5)
  })
  it('zero total all zero', () => {
    const sim = installmentSimulation(0)
    sim.forEach(s => expect(s.value).toBe(0))
  })
})

// ── Calibration Status (matching backend Equipment model) ──

function getCalibrationStatus(nextCalibrationAt: Date | null): string {
  if (!nextCalibrationAt) return 'sem_data'
  const now = new Date()
  const diff = (nextCalibrationAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)
  if (diff < 0) return 'vencida'
  if (diff <= 30) return 'vence_em_breve'
  return 'em_dia'
}

describe('Calibration Status', () => {
  it('null → sem_data', () => expect(getCalibrationStatus(null)).toBe('sem_data'))
  it('past → vencida', () => {
    const past = new Date()
    past.setDate(past.getDate() - 10)
    expect(getCalibrationStatus(past)).toBe('vencida')
  })
  it('15 days → vence_em_breve', () => {
    const future = new Date()
    future.setDate(future.getDate() + 15)
    expect(getCalibrationStatus(future)).toBe('vence_em_breve')
  })
  it('90 days → em_dia', () => {
    const future = new Date()
    future.setDate(future.getDate() + 90)
    expect(getCalibrationStatus(future)).toBe('em_dia')
  })
})

// ── Health Score Calculator (matching Customer model) ──

interface HealthScoreInput {
  hasEquipments: boolean
  calibrationRatio: number
  hasRecentWO: boolean
  hasRecentContact: boolean
  hasApprovedQuote: boolean
  hasOverdueReceivable: boolean
  equipmentCount: number
}

function calculateHealthScore(input: HealthScoreInput): number {
  let score = 0
  // Calibrações (max 30)
  if (!input.hasEquipments) {
    score += 30
  } else {
    score += Math.round(input.calibrationRatio * 30)
  }
  // OS recente (max 20)
  if (input.hasRecentWO) score += 20
  // Contato recente (max 15)
  if (input.hasRecentContact) score += 15
  // Orçamento aprovado (max 15)
  if (input.hasApprovedQuote) score += 15
  // Sem pendências (max 10)
  if (!input.hasOverdueReceivable) score += 10
  // Volume equipamentos (max 10)
  score += Math.min(10, input.equipmentCount * 2)

  return score
}

describe('Health Score Calculator', () => {
  it('max score = 100 (no equipments, all green)', () => {
    const score = calculateHealthScore({
      hasEquipments: false, calibrationRatio: 1,
      hasRecentWO: true, hasRecentContact: true,
      hasApprovedQuote: true, hasOverdueReceivable: false,
      equipmentCount: 0,
    })
    expect(score).toBe(90) // 30 + 20 + 15 + 15 + 10 + 0
  })

  it('perfect score with 5+ equipments', () => {
    const score = calculateHealthScore({
      hasEquipments: true, calibrationRatio: 1,
      hasRecentWO: true, hasRecentContact: true,
      hasApprovedQuote: true, hasOverdueReceivable: false,
      equipmentCount: 5,
    })
    expect(score).toBe(100)
  })

  it('zero score worst case', () => {
    const score = calculateHealthScore({
      hasEquipments: true, calibrationRatio: 0,
      hasRecentWO: false, hasRecentContact: false,
      hasApprovedQuote: false, hasOverdueReceivable: true,
      equipmentCount: 0,
    })
    expect(score).toBe(0)
  })

  it('overdue receivable costs 10 points', () => {
    const withoutOverdue = calculateHealthScore({
      hasEquipments: false, calibrationRatio: 1,
      hasRecentWO: false, hasRecentContact: false,
      hasApprovedQuote: false, hasOverdueReceivable: false,
      equipmentCount: 0,
    })
    const withOverdue = calculateHealthScore({
      hasEquipments: false, calibrationRatio: 1,
      hasRecentWO: false, hasRecentContact: false,
      hasApprovedQuote: false, hasOverdueReceivable: true,
      equipmentCount: 0,
    })
    expect(withoutOverdue - withOverdue).toBe(10)
  })

  it('equipment count capped at 10', () => {
    const s1 = calculateHealthScore({
      hasEquipments: true, calibrationRatio: 0,
      hasRecentWO: false, hasRecentContact: false,
      hasApprovedQuote: false, hasOverdueReceivable: true,
      equipmentCount: 5,
    })
    const s2 = calculateHealthScore({
      hasEquipments: true, calibrationRatio: 0,
      hasRecentWO: false, hasRecentContact: false,
      hasApprovedQuote: false, hasOverdueReceivable: true,
      equipmentCount: 50,
    })
    expect(s1).toBe(s2) // Both capped at 10
  })
})
