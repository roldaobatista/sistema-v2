import { describe, it, expect } from 'vitest'

// â”€â”€ Work Order State Machine (comprehensive — matching backend WorkOrderStatus) â”€â”€

type WOStatus = 'pending' | 'quoted' | 'approved' | 'in_progress' | 'completed' | 'cancelled' | 'invoiced'

const WO_TRANSITIONS: Record<WOStatus, WOStatus[]> = {
  pending: ['quoted', 'in_progress', 'cancelled'],
  quoted: ['approved', 'cancelled'],
  approved: ['in_progress', 'cancelled'],
  in_progress: ['completed', 'cancelled'],
  completed: ['invoiced'],
  invoiced: [],
  cancelled: [],
}

const WO_LABELS: Record<WOStatus, string> = {
  pending: 'Pendente',
  quoted: 'Orçado',
  approved: 'Aprovado',
  in_progress: 'Em Andamento',
  completed: 'Concluído',
  invoiced: 'Faturado',
  cancelled: 'Cancelado',
}

const WO_COLORS: Record<WOStatus, string> = {
  pending: '#94a3b8',
  quoted: '#60a5fa',
  approved: '#34d399',
  in_progress: '#fbbf24',
  completed: '#10b981',
  invoiced: '#0d9488',
  cancelled: '#ef4444',
}

function canTransition(from: WOStatus, to: WOStatus): boolean {
  return WO_TRANSITIONS[from]?.includes(to) ?? false
}

function isTerminal(status: WOStatus): boolean {
  return WO_TRANSITIONS[status]?.length === 0
}

function isActive(status: WOStatus): boolean {
  return ['pending', 'quoted', 'approved', 'in_progress'].includes(status)
}

function getAvailableTransitions(status: WOStatus): WOStatus[] {
  return WO_TRANSITIONS[status] ?? []
}

describe('WO State Machine — Transitions', () => {
  it('pending → quoted', () => expect(canTransition('pending', 'quoted')).toBe(true))
  it('pending → in_progress', () => expect(canTransition('pending', 'in_progress')).toBe(true))
  it('pending → cancelled', () => expect(canTransition('pending', 'cancelled')).toBe(true))
  it('pending → completed (invalid)', () => expect(canTransition('pending', 'completed')).toBe(false))
  it('quoted → approved', () => expect(canTransition('quoted', 'approved')).toBe(true))
  it('quoted → in_progress (invalid)', () => expect(canTransition('quoted', 'in_progress')).toBe(false))
  it('approved → in_progress', () => expect(canTransition('approved', 'in_progress')).toBe(true))
  it('in_progress → completed', () => expect(canTransition('in_progress', 'completed')).toBe(true))
  it('completed → invoiced', () => expect(canTransition('completed', 'invoiced')).toBe(true))
  it('completed → cancelled (invalid)', () => expect(canTransition('completed', 'cancelled')).toBe(false))
  it('invoiced → nowhere', () => expect(canTransition('invoiced', 'pending')).toBe(false))
  it('cancelled → nowhere', () => expect(canTransition('cancelled', 'pending')).toBe(false))
})

describe('WO Terminal States', () => {
  it('invoiced is terminal', () => expect(isTerminal('invoiced')).toBe(true))
  it('cancelled is terminal', () => expect(isTerminal('cancelled')).toBe(true))
  it('pending is NOT terminal', () => expect(isTerminal('pending')).toBe(false))
  it('completed is NOT terminal', () => expect(isTerminal('completed')).toBe(false))
})

describe('WO Active States', () => {
  it('pending is active', () => expect(isActive('pending')).toBe(true))
  it('in_progress is active', () => expect(isActive('in_progress')).toBe(true))
  it('completed is NOT active', () => expect(isActive('completed')).toBe(false))
  it('cancelled is NOT active', () => expect(isActive('cancelled')).toBe(false))
})

describe('WO Labels', () => {
  it('all 7 statuses', () => expect(Object.keys(WO_LABELS)).toHaveLength(7))
  it('pending → Pendente', () => expect(WO_LABELS.pending).toBe('Pendente'))
  it('invoiced → Faturado', () => expect(WO_LABELS.invoiced).toBe('Faturado'))
})

describe('WO Colors', () => {
  it('all 7 colors', () => expect(Object.keys(WO_COLORS)).toHaveLength(7))
  it('cancelled is red', () => expect(WO_COLORS.cancelled).toContain('ef4444'))
  it('completed is green', () => expect(WO_COLORS.completed).toContain('10b981'))
})

describe('Available Transitions', () => {
  it('pending has 3 transitions', () => expect(getAvailableTransitions('pending')).toHaveLength(3))
  it('invoiced has 0', () => expect(getAvailableTransitions('invoiced')).toHaveLength(0))
  it('cancelled has 0', () => expect(getAvailableTransitions('cancelled')).toHaveLength(0))
  it('completed has 1 (invoiced)', () => expect(getAvailableTransitions('completed')).toEqual(['invoiced']))
})

// â”€â”€ Financial summary charts â”€â”€

interface FinancialSeries {
  month: string
  revenue: number
  expenses: number
  profit: number
}

function calculateProfitMargin(revenue: number, profit: number): number {
  if (revenue === 0) return 0
  return Math.round((profit / revenue) * 10000) / 100
}

function getMonthlyTrend(data: FinancialSeries[]): 'up' | 'down' | 'stable' {
  if (data.length < 2) return 'stable'
  const last = data[data.length - 1].profit
  const prev = data[data.length - 2].profit
  if (last > prev * 1.05) return 'up'
  if (last < prev * 0.95) return 'down'
  return 'stable'
}

function aggregateFinancials(data: FinancialSeries[]) {
  return {
    totalRevenue: data.reduce((s, d) => s + d.revenue, 0),
    totalExpenses: data.reduce((s, d) => s + d.expenses, 0),
    totalProfit: data.reduce((s, d) => s + d.profit, 0),
    avgMonthlyRevenue: data.length ? data.reduce((s, d) => s + d.revenue, 0) / data.length : 0,
    bestMonth: data.reduce((best, curr) => curr.profit > best.profit ? curr : best, data[0]),
    worstMonth: data.reduce((worst, curr) => curr.profit < worst.profit ? curr : worst, data[0]),
  }
}

describe('Profit Margin', () => {
  it('50% margin', () => expect(calculateProfitMargin(100000, 50000)).toBe(50))
  it('0% margin', () => expect(calculateProfitMargin(100000, 0)).toBe(0))
  it('negative margin', () => expect(calculateProfitMargin(100000, -20000)).toBe(-20))
  it('zero revenue', () => expect(calculateProfitMargin(0, 0)).toBe(0))
  it('100% margin (unusual)', () => expect(calculateProfitMargin(50000, 50000)).toBe(100))
})

describe('Monthly Trend', () => {
  it('up trend', () => {
    const data: FinancialSeries[] = [
      { month: 'Jan', revenue: 100000, expenses: 60000, profit: 40000 },
      { month: 'Feb', revenue: 120000, expenses: 60000, profit: 60000 },
    ]
    expect(getMonthlyTrend(data)).toBe('up')
  })
  it('down trend', () => {
    const data: FinancialSeries[] = [
      { month: 'Jan', revenue: 120000, expenses: 60000, profit: 60000 },
      { month: 'Feb', revenue: 80000, expenses: 60000, profit: 20000 },
    ]
    expect(getMonthlyTrend(data)).toBe('down')
  })
  it('stable', () => {
    const data: FinancialSeries[] = [
      { month: 'Jan', revenue: 100000, expenses: 60000, profit: 40000 },
      { month: 'Feb', revenue: 100000, expenses: 59000, profit: 41000 },
    ]
    expect(getMonthlyTrend(data)).toBe('stable')
  })
  it('single month → stable', () => {
    expect(getMonthlyTrend([{ month: 'Jan', revenue: 100000, expenses: 60000, profit: 40000 }])).toBe('stable')
  })
})

describe('Aggregate Financials', () => {
  const data: FinancialSeries[] = [
    { month: 'Jan', revenue: 100000, expenses: 60000, profit: 40000 },
    { month: 'Feb', revenue: 80000, expenses: 50000, profit: 30000 },
    { month: 'Mar', revenue: 120000, expenses: 70000, profit: 50000 },
  ]
  it('total revenue', () => expect(aggregateFinancials(data).totalRevenue).toBe(300000))
  it('total expenses', () => expect(aggregateFinancials(data).totalExpenses).toBe(180000))
  it('total profit', () => expect(aggregateFinancials(data).totalProfit).toBe(120000))
  it('avg monthly revenue', () => expect(aggregateFinancials(data).avgMonthlyRevenue).toBe(100000))
  it('best month is March', () => expect(aggregateFinancials(data).bestMonth.month).toBe('Mar'))
  it('worst month is February', () => expect(aggregateFinancials(data).worstMonth.month).toBe('Feb'))
})

// â”€â”€ Equipment Calibration Status â”€â”€

type CalibrationStatus = 'valid' | 'expiring_soon' | 'expired' | 'never_calibrated'

function getCalibrationStatus(
  lastCalibrationDate: Date | null,
  intervalMonths: number,
  warningDays: number = 30
): CalibrationStatus {
  if (!lastCalibrationDate) return 'never_calibrated'
  const nextDue = new Date(lastCalibrationDate)
  nextDue.setMonth(nextDue.getMonth() + intervalMonths)
  const now = new Date()
  if (nextDue < now) return 'expired'
  const warningDate = new Date(nextDue)
  warningDate.setDate(warningDate.getDate() - warningDays)
  if (now >= warningDate) return 'expiring_soon'
  return 'valid'
}

describe('Calibration Status', () => {
  it('never calibrated', () => expect(getCalibrationStatus(null, 12)).toBe('never_calibrated'))
  it('valid (recent)', () => {
    const recent = new Date(); recent.setMonth(recent.getMonth() - 1)
    expect(getCalibrationStatus(recent, 12)).toBe('valid')
  })
  it('expired', () => {
    const old = new Date(); old.setFullYear(old.getFullYear() - 2)
    expect(getCalibrationStatus(old, 12)).toBe('expired')
  })
  it('expiring soon (within 30 days)', () => {
    const almostExpired = new Date(); almostExpired.setMonth(almostExpired.getMonth() - 11)
    almostExpired.setDate(almostExpired.getDate() - 10) // ~11 months 10 days ago
    expect(getCalibrationStatus(almostExpired, 12)).toBe('expiring_soon')
  })
})
