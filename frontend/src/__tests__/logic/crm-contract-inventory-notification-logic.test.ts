import { describe, it, expect } from 'vitest'

// ── CRM Pipeline Kanban Helpers ──

interface DealCard {
  id: number
  title: string
  value: number
  stageId: number
  customerId: number
  customerName: string
  probability: number
  daysInStage: number
  assignedTo: string | null
}

function getDealsByStage(deals: DealCard[], stageId: number): DealCard[] {
  return deals.filter(d => d.stageId === stageId)
}

function getStageTotalValue(deals: DealCard[], stageId: number): number {
  return deals.filter(d => d.stageId === stageId).reduce((sum, d) => sum + d.value, 0)
}

function getWeightedPipelineValue(deals: DealCard[]): number {
  return Math.round(deals.reduce((sum, d) => sum + d.value * (d.probability / 100), 0) * 100) / 100
}

function getAverageDaysInStage(deals: DealCard[], stageId: number): number {
  const stageDeals = deals.filter(d => d.stageId === stageId)
  if (stageDeals.length === 0) return 0
  return Math.round(stageDeals.reduce((sum, d) => sum + d.daysInStage, 0) / stageDeals.length)
}

function getStaleDeals(deals: DealCard[], maxDays: number): DealCard[] {
  return deals.filter(d => d.daysInStage > maxDays)
}

const testDeals: DealCard[] = [
  { id: 1, title: 'Deal A', value: 50000, stageId: 1, customerId: 1, customerName: 'Emp A', probability: 25, daysInStage: 5, assignedTo: 'João' },
  { id: 2, title: 'Deal B', value: 30000, stageId: 1, customerId: 2, customerName: 'Emp B', probability: 25, daysInStage: 45, assignedTo: null },
  { id: 3, title: 'Deal C', value: 80000, stageId: 2, customerId: 3, customerName: 'Emp C', probability: 50, daysInStage: 10, assignedTo: 'Maria' },
  { id: 4, title: 'Deal D', value: 20000, stageId: 3, customerId: 4, customerName: 'Emp D', probability: 75, daysInStage: 3, assignedTo: 'João' },
  { id: 5, title: 'Deal E', value: 100000, stageId: 4, customerId: 5, customerName: 'Emp E', probability: 90, daysInStage: 2, assignedTo: 'Maria' },
]

describe('CRM Pipeline — Deals by Stage', () => {
  it('stage 1 has 2 deals', () => expect(getDealsByStage(testDeals, 1)).toHaveLength(2))
  it('stage 2 has 1 deal', () => expect(getDealsByStage(testDeals, 2)).toHaveLength(1))
  it('stage 5 has 0', () => expect(getDealsByStage(testDeals, 5)).toHaveLength(0))
})

describe('CRM Pipeline — Stage Total', () => {
  it('stage 1 total = 80000', () => expect(getStageTotalValue(testDeals, 1)).toBe(80000))
  it('stage 4 total = 100000', () => expect(getStageTotalValue(testDeals, 4)).toBe(100000))
})

describe('CRM Pipeline — Weighted Value', () => {
  it('weighted = 50000×0.25 + 30000×0.25 + 80000×0.5 + 20000×0.75 + 100000×0.9', () => {
    const expected = 12500 + 7500 + 40000 + 15000 + 90000 // = 165000
    expect(getWeightedPipelineValue(testDeals)).toBe(165000)
  })
})

describe('CRM Pipeline — Avg Days in Stage', () => {
  it('stage 1 avg = 25', () => expect(getAverageDaysInStage(testDeals, 1)).toBe(25))
  it('stage 2 avg = 10', () => expect(getAverageDaysInStage(testDeals, 2)).toBe(10))
  it('empty stage = 0', () => expect(getAverageDaysInStage(testDeals, 99)).toBe(0))
})

describe('CRM Pipeline — Stale Deals', () => {
  it('stale > 30 days', () => {
    const stale = getStaleDeals(testDeals, 30)
    expect(stale).toHaveLength(1)
    expect(stale[0].title).toBe('Deal B')
  })
  it('none stale > 100 days', () => expect(getStaleDeals(testDeals, 100)).toHaveLength(0))
})

// ── Contract duration helpers ──

function calculateContractMonths(startDate: Date, endDate: Date): number {
  const diff = (endDate.getFullYear() - startDate.getFullYear()) * 12 +
    (endDate.getMonth() - startDate.getMonth())
  return Math.max(0, diff)
}

function getContractStatus(startDate: Date, endDate: Date): 'active' | 'expired' | 'upcoming' {
  const now = new Date()
  if (now < startDate) return 'upcoming'
  if (now > endDate) return 'expired'
  return 'active'
}

function calculateContractMonthlyValue(totalValue: number, months: number): number {
  if (months <= 0) return 0
  return Math.round((totalValue / months) * 100) / 100
}

describe('Contract Months', () => {
  it('12 months', () => expect(calculateContractMonths(new Date(2026, 0, 1), new Date(2027, 0, 1))).toBe(12))
  it('6 months', () => expect(calculateContractMonths(new Date(2026, 0, 1), new Date(2026, 6, 1))).toBe(6))
  it('0 months (same date)', () => expect(calculateContractMonths(new Date(2026, 0, 1), new Date(2026, 0, 1))).toBe(0))
  it('negative → 0', () => expect(calculateContractMonths(new Date(2027, 0, 1), new Date(2026, 0, 1))).toBe(0))
})

describe('Contract Status', () => {
  it('active', () => {
    const start = new Date(); start.setMonth(start.getMonth() - 3)
    const end = new Date(); end.setMonth(end.getMonth() + 9)
    expect(getContractStatus(start, end)).toBe('active')
  })
  it('expired', () => {
    const start = new Date(2020, 0, 1)
    const end = new Date(2021, 0, 1)
    expect(getContractStatus(start, end)).toBe('expired')
  })
  it('upcoming', () => {
    const start = new Date(); start.setMonth(start.getMonth() + 1)
    const end = new Date(); end.setMonth(end.getMonth() + 13)
    expect(getContractStatus(start, end)).toBe('upcoming')
  })
})

describe('Contract Monthly Value', () => {
  it('12000 / 12 = 1000', () => expect(calculateContractMonthlyValue(12000, 12)).toBe(1000))
  it('10000 / 3 = 3333.33', () => expect(calculateContractMonthlyValue(10000, 3)).toBe(3333.33))
  it('0 months → 0', () => expect(calculateContractMonthlyValue(12000, 0)).toBe(0))
})

// ── Inventory alert helpers ──

function getStockAlertLevel(current: number, minimum: number): 'ok' | 'low' | 'critical' | 'out' {
  if (current === 0) return 'out'
  if (current <= minimum * 0.5) return 'critical'
  if (current <= minimum) return 'low'
  return 'ok'
}

function calculateReorderQuantity(minimum: number, optimal: number, current: number): number {
  if (current >= minimum) return 0
  return Math.max(0, optimal - current)
}

describe('Stock Alert Level', () => {
  it('ok — above minimum', () => expect(getStockAlertLevel(50, 10)).toBe('ok'))
  it('low — at minimum', () => expect(getStockAlertLevel(10, 10)).toBe('low'))
  it('critical — half minimum', () => expect(getStockAlertLevel(3, 10)).toBe('critical'))
  it('out — zero', () => expect(getStockAlertLevel(0, 10)).toBe('out'))
})

describe('Reorder Quantity', () => {
  it('needs reorder', () => expect(calculateReorderQuantity(10, 50, 5)).toBe(45))
  it('no reorder needed', () => expect(calculateReorderQuantity(10, 50, 15)).toBe(0))
  it('exactly at minimum', () => expect(calculateReorderQuantity(10, 50, 10)).toBe(0))
})

// ── Notification grouping ──

interface NotificationGroup {
  type: string
  count: number
  latestAt: string
}

function groupNotifications(notifications: { type: string; created_at: string }[]): NotificationGroup[] {
  const groups: Record<string, NotificationGroup> = {}
  for (const n of notifications) {
    if (!groups[n.type]) {
      groups[n.type] = { type: n.type, count: 0, latestAt: n.created_at }
    }
    groups[n.type].count++
    if (n.created_at > groups[n.type].latestAt) {
      groups[n.type].latestAt = n.created_at
    }
  }
  return Object.values(groups).sort((a, b) => b.count - a.count)
}

describe('Notification Grouping', () => {
  const notifs = [
    { type: 'wo_assigned', created_at: '2026-03-15T10:00:00' },
    { type: 'wo_assigned', created_at: '2026-03-16T10:00:00' },
    { type: 'deal_won', created_at: '2026-03-15T08:00:00' },
    { type: 'wo_assigned', created_at: '2026-03-14T10:00:00' },
    { type: 'invoice_paid', created_at: '2026-03-16T12:00:00' },
  ]

  it('groups correctly', () => {
    const groups = groupNotifications(notifs)
    expect(groups[0].type).toBe('wo_assigned')
    expect(groups[0].count).toBe(3)
  })
  it('sorted by count desc', () => {
    const groups = groupNotifications(notifs)
    expect(groups[0].count).toBeGreaterThanOrEqual(groups[1].count)
  })
  it('latest date', () => {
    const groups = groupNotifications(notifs)
    const wo = groups.find(g => g.type === 'wo_assigned')!
    expect(wo.latestAt).toBe('2026-03-16T10:00:00')
  })
})
