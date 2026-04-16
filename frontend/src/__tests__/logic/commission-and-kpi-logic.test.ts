import { describe, it, expect } from 'vitest'

// ── Commission Calculator (matching backend CommissionService) ──

type CommissionCalcType = 'percentage' | 'fixed' | 'net_percentage'

interface CommissionContext {
  gross: number
  cost: number
  expenses: number
  displacement: number
}

function calculateCommission(
  total: number,
  calcType: CommissionCalcType,
  percentage: number,
  fixedAmount: number,
  context: CommissionContext
): number {
  switch (calcType) {
    case 'percentage':
      return Math.round(total * (percentage / 100) * 100) / 100
    case 'fixed':
      return fixedAmount
    case 'net_percentage': {
      const netValue = total - context.cost - context.expenses - context.displacement
      return Math.max(0, Math.round(netValue * (percentage / 100) * 100) / 100)
    }
  }
}

function applySplitDivisor(amount: number, divisor: number): number {
  if (divisor <= 1) return amount
  return Math.round((amount / divisor) * 100) / 100
}

function applyCampaignMultiplier(amount: number, multiplier: number): number {
  return Math.round(amount * multiplier * 100) / 100
}

describe('Commission calculateCommission', () => {
  const ctx: CommissionContext = { gross: 10000, cost: 3000, expenses: 500, displacement: 200 }

  it('percentage 10% of 10000 = 1000', () => {
    expect(calculateCommission(10000, 'percentage', 10, 0, ctx)).toBe(1000)
  })
  it('percentage 5% of 8000 = 400', () => {
    expect(calculateCommission(8000, 'percentage', 5, 0, ctx)).toBe(400)
  })
  it('fixed R$200', () => {
    expect(calculateCommission(10000, 'fixed', 0, 200, ctx)).toBe(200)
  })
  it('net_percentage 10%: net = 10000 - 3000 - 500 - 200 = 6300 → 630', () => {
    expect(calculateCommission(10000, 'net_percentage', 10, 0, ctx)).toBe(630)
  })
  it('net_percentage never negative', () => {
    const highCostCtx: CommissionContext = { gross: 100, cost: 5000, expenses: 500, displacement: 200 }
    expect(calculateCommission(100, 'net_percentage', 10, 0, highCostCtx)).toBe(0)
  })
  it('percentage 0% = 0', () => {
    expect(calculateCommission(10000, 'percentage', 0, 0, ctx)).toBe(0)
  })
})

describe('Commission applySplitDivisor', () => {
  it('no split (1) → same amount', () => expect(applySplitDivisor(1000, 1)).toBe(1000))
  it('split by 2 → 500', () => expect(applySplitDivisor(1000, 2)).toBe(500))
  it('split by 3 → 333.33', () => expect(applySplitDivisor(1000, 3)).toBe(333.33))
  it('split by 0 → same amount', () => expect(applySplitDivisor(1000, 0)).toBe(1000))
})

describe('Commission applyCampaignMultiplier', () => {
  it('multiplier 1 → same', () => expect(applyCampaignMultiplier(500, 1)).toBe(500))
  it('multiplier 1.5 → 750', () => expect(applyCampaignMultiplier(500, 1.5)).toBe(750))
  it('multiplier 2 → 1000', () => expect(applyCampaignMultiplier(500, 2)).toBe(1000))
})

// ── Calibration Interval Calculator ──

function getNextCalibrationDate(lastCalibration: Date, intervalMonths: number): Date {
  const next = new Date(lastCalibration)
  next.setMonth(next.getMonth() + intervalMonths)
  return next
}

function daysUntilCalibration(nextCalibration: Date): number {
  const now = new Date()
  const diff = nextCalibration.getTime() - now.getTime()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
}

function getCalibrationUrgency(daysLeft: number): 'overdue' | 'urgent' | 'warning' | 'ok' {
  if (daysLeft < 0) return 'overdue'
  if (daysLeft <= 7) return 'urgent'
  if (daysLeft <= 30) return 'warning'
  return 'ok'
}

describe('Calibration Interval Calculator', () => {
  it('12 months interval', () => {
    const last = new Date(2025, 0, 15) // Jan 15
    const next = getNextCalibrationDate(last, 12)
    expect(next.getFullYear()).toBe(2026)
    expect(next.getMonth()).toBe(0) // January
  })
  it('6 months interval', () => {
    const last = new Date(2025, 3, 1) // Apr 1
    const next = getNextCalibrationDate(last, 6)
    expect(next.getMonth()).toBe(9) // October
  })
  it('1 month interval', () => {
    const last = new Date(2025, 5, 15) // Jun 15
    const next = getNextCalibrationDate(last, 1)
    expect(next.getMonth()).toBe(6) // July
  })
})

describe('Calibration Urgency', () => {
  it('-5 days → overdue', () => expect(getCalibrationUrgency(-5)).toBe('overdue'))
  it('0 days → urgent', () => expect(getCalibrationUrgency(0)).toBe('urgent'))
  it('5 days → urgent', () => expect(getCalibrationUrgency(5)).toBe('urgent'))
  it('15 days → warning', () => expect(getCalibrationUrgency(15)).toBe('warning'))
  it('30 days → warning', () => expect(getCalibrationUrgency(30)).toBe('warning'))
  it('60 days → ok', () => expect(getCalibrationUrgency(60)).toBe('ok'))
})

// ── KPI Dashboard Calculations ──

interface DashboardKPIs {
  totalRevenue: number
  totalCost: number
  activeWOs: number
  completedWOs: number
  pendingQuotes: number
  overdueReceivables: number
}

function calculateProfitMargin(revenue: number, cost: number): number {
  if (revenue <= 0) return 0
  return Math.round(((revenue - cost) / revenue) * 1000) / 10
}

function calculateCompletionRate(completed: number, total: number): number {
  if (total <= 0) return 0
  return Math.round((completed / total) * 1000) / 10
}

function getOverdueAlertLevel(count: number): 'none' | 'low' | 'medium' | 'high' {
  if (count === 0) return 'none'
  if (count <= 3) return 'low'
  if (count <= 10) return 'medium'
  return 'high'
}

describe('Dashboard KPIs', () => {
  it('profit margin 75%', () => expect(calculateProfitMargin(10000, 2500)).toBe(75))
  it('profit margin 0% when revenue zero', () => expect(calculateProfitMargin(0, 100)).toBe(0))
  it('profit margin 100%', () => expect(calculateProfitMargin(1000, 0)).toBe(100))
  it('profit margin negative cost > revenue', () => expect(calculateProfitMargin(100, 200)).toBe(-100))
  it('completion rate 80%', () => expect(calculateCompletionRate(80, 100)).toBe(80))
  it('completion rate 0% no items', () => expect(calculateCompletionRate(0, 0)).toBe(0))
  it('completion rate 100%', () => expect(calculateCompletionRate(50, 50)).toBe(100))
})

describe('Overdue Alert Level', () => {
  it('0 → none', () => expect(getOverdueAlertLevel(0)).toBe('none'))
  it('2 → low', () => expect(getOverdueAlertLevel(2)).toBe('low'))
  it('5 → medium', () => expect(getOverdueAlertLevel(5)).toBe('medium'))
  it('15 → high', () => expect(getOverdueAlertLevel(15)).toBe('high'))
})

// ── Work Order Priority Sorting ──

const PRIORITY_ORDER: Record<string, number> = {
  urgent: 0,
  high: 1,
  medium: 2,
  low: 3,
}

interface WOItem {
  id: number
  priority: string
  scheduledDate: Date | null
}

function sortWOByPriority(items: WOItem[]): WOItem[] {
  return [...items].sort((a, b) => {
    const pa = PRIORITY_ORDER[a.priority] ?? 99
    const pb = PRIORITY_ORDER[b.priority] ?? 99
    if (pa !== pb) return pa - pb
    // Same priority → sort by date
    const da = a.scheduledDate?.getTime() ?? Infinity
    const db = b.scheduledDate?.getTime() ?? Infinity
    return da - db
  })
}

describe('WO Priority Sorting', () => {
  it('urgent before high', () => {
    const items: WOItem[] = [
      { id: 1, priority: 'high', scheduledDate: new Date() },
      { id: 2, priority: 'urgent', scheduledDate: new Date() },
    ]
    const sorted = sortWOByPriority(items)
    expect(sorted[0].id).toBe(2)
  })
  it('same priority sorted by date', () => {
    const earlier = new Date(2025, 1, 1)
    const later = new Date(2025, 6, 1)
    const items: WOItem[] = [
      { id: 1, priority: 'medium', scheduledDate: later },
      { id: 2, priority: 'medium', scheduledDate: earlier },
    ]
    const sorted = sortWOByPriority(items)
    expect(sorted[0].id).toBe(2)
  })
  it('null date goes last', () => {
    const items: WOItem[] = [
      { id: 1, priority: 'low', scheduledDate: null },
      { id: 2, priority: 'low', scheduledDate: new Date(2025, 1, 1) },
    ]
    const sorted = sortWOByPriority(items)
    expect(sorted[0].id).toBe(2)
  })
})
