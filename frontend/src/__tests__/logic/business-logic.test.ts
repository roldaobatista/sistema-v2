import { describe, it, expect } from 'vitest'

// ── State Machine Logic ──

type WorkOrderStatus = 'open' | 'in_progress' | 'completed' | 'cancelled'

const validTransitions: Record<WorkOrderStatus, WorkOrderStatus[]> = {
  open: ['in_progress', 'cancelled'],
  in_progress: ['completed', 'open', 'cancelled'],
  completed: ['open'],
  cancelled: ['open'],
}

function canTransition(from: WorkOrderStatus, to: WorkOrderStatus): boolean {
  return validTransitions[from]?.includes(to) ?? false
}

describe('WorkOrder State Machine', () => {
  it('open → in_progress allowed', () => { expect(canTransition('open', 'in_progress')).toBe(true) })
  it('open → completed NOT allowed', () => { expect(canTransition('open', 'completed')).toBe(false) })
  it('open → cancelled allowed', () => { expect(canTransition('open', 'cancelled')).toBe(true) })
  it('in_progress → completed allowed', () => { expect(canTransition('in_progress', 'completed')).toBe(true) })
  it('in_progress → open allowed', () => { expect(canTransition('in_progress', 'open')).toBe(true) })
  it('completed → open (reopen) allowed', () => { expect(canTransition('completed', 'open')).toBe(true) })
  it('completed → cancelled NOT allowed', () => { expect(canTransition('completed', 'cancelled')).toBe(false) })
  it('cancelled → open (reopen) allowed', () => { expect(canTransition('cancelled', 'open')).toBe(true) })
  it('cancelled → completed NOT allowed', () => { expect(canTransition('cancelled', 'completed')).toBe(false) })
})

// ── Dashboard KPI Calculations ──

interface DashboardData {
  workOrders: { total: number; open: number; completed: number; cancelled: number }
  revenue: { currentMonth: number; lastMonth: number }
  customers: { total: number; active: number }
  quotes: { total: number; approved: number; pending: number }
}

describe('Dashboard KPI Calculations', () => {
  const data: DashboardData = {
    workOrders: { total: 100, open: 30, completed: 60, cancelled: 10 },
    revenue: { currentMonth: 150000, lastMonth: 120000 },
    customers: { total: 200, active: 180 },
    quotes: { total: 50, approved: 30, pending: 15 },
  }

  it('calculates completion rate', () => {
    const rate = (data.workOrders.completed / data.workOrders.total) * 100
    expect(rate).toBe(60)
  })

  it('calculates cancellation rate', () => {
    const rate = (data.workOrders.cancelled / data.workOrders.total) * 100
    expect(rate).toBe(10)
  })

  it('calculates revenue growth', () => {
    const growth = ((data.revenue.currentMonth - data.revenue.lastMonth) / data.revenue.lastMonth) * 100
    expect(growth).toBe(25)
  })

  it('calculates customer retention rate', () => {
    const rate = (data.customers.active / data.customers.total) * 100
    expect(rate).toBe(90)
  })

  it('calculates quote conversion rate', () => {
    const rate = (data.quotes.approved / data.quotes.total) * 100
    expect(rate).toBe(60)
  })

  it('handles zero total', () => {
    const total = 0
    const rate = total === 0 ? 0 : (10 / total)
    expect(rate).toBe(0)
  })
})

// ── Table Sorting Logic ──

interface SortConfig { key: string; direction: 'asc' | 'desc' }

function toggleSort(current: SortConfig | null, key: string): SortConfig {
  if (!current || current.key !== key) return { key, direction: 'asc' }
  if (current.direction === 'asc') return { key, direction: 'desc' }
  return { key, direction: 'asc' }
}

describe('Table Sort Logic', () => {
  it('first click sorts ascending', () => {
    const result = toggleSort(null, 'name')
    expect(result).toEqual({ key: 'name', direction: 'asc' })
  })

  it('second click on same key sorts descending', () => {
    const result = toggleSort({ key: 'name', direction: 'asc' }, 'name')
    expect(result).toEqual({ key: 'name', direction: 'desc' })
  })

  it('third click on same key sorts ascending again', () => {
    const result = toggleSort({ key: 'name', direction: 'desc' }, 'name')
    expect(result).toEqual({ key: 'name', direction: 'asc' })
  })

  it('clicking different key resets to ascending', () => {
    const result = toggleSort({ key: 'name', direction: 'desc' }, 'email')
    expect(result).toEqual({ key: 'email', direction: 'asc' })
  })
})

// ── Filter Logic ──

interface FilterConfig {
  search: string
  status: string[]
  priority: string[]
  dateFrom: string | null
  dateTo: string | null
}

function isFilterActive(filter: FilterConfig): boolean {
  return filter.search.length > 0
    || filter.status.length > 0
    || filter.priority.length > 0
    || filter.dateFrom !== null
    || filter.dateTo !== null
}

function countActiveFilters(filter: FilterConfig): number {
  let count = 0
  if (filter.search.length > 0) count++
  if (filter.status.length > 0) count++
  if (filter.priority.length > 0) count++
  if (filter.dateFrom) count++
  if (filter.dateTo) count++
  return count
}

describe('Filter Logic', () => {
  const emptyFilter: FilterConfig = { search: '', status: [], priority: [], dateFrom: null, dateTo: null }

  it('empty filter is not active', () => {
    expect(isFilterActive(emptyFilter)).toBe(false)
  })

  it('search makes filter active', () => {
    expect(isFilterActive({ ...emptyFilter, search: 'test' })).toBe(true)
  })

  it('status makes filter active', () => {
    expect(isFilterActive({ ...emptyFilter, status: ['open'] })).toBe(true)
  })

  it('counts active filters correctly', () => {
    const f: FilterConfig = { search: 'x', status: ['open'], priority: [], dateFrom: '2026-01-01', dateTo: null }
    expect(countActiveFilters(f)).toBe(3)
  })

  it('zero active filters for empty', () => {
    expect(countActiveFilters(emptyFilter)).toBe(0)
  })
})

// ── Permission Helper ──

type UserRole = 'admin' | 'manager' | 'technician' | 'viewer'

const permissions: Record<UserRole, string[]> = {
  admin: ['create', 'read', 'update', 'delete', 'manage'],
  manager: ['create', 'read', 'update', 'delete'],
  technician: ['read', 'update'],
  viewer: ['read'],
}

function hasPermission(role: UserRole, action: string): boolean {
  return permissions[role]?.includes(action) ?? false
}

describe('Permission Helper', () => {
  it('admin can create', () => { expect(hasPermission('admin', 'create')).toBe(true) })
  it('admin can manage', () => { expect(hasPermission('admin', 'manage')).toBe(true) })
  it('manager can delete', () => { expect(hasPermission('manager', 'delete')).toBe(true) })
  it('manager cannot manage', () => { expect(hasPermission('manager', 'manage')).toBe(false) })
  it('technician can update', () => { expect(hasPermission('technician', 'update')).toBe(true) })
  it('technician cannot delete', () => { expect(hasPermission('technician', 'delete')).toBe(false) })
  it('viewer can only read', () => { expect(hasPermission('viewer', 'read')).toBe(true) })
  it('viewer cannot create', () => { expect(hasPermission('viewer', 'create')).toBe(false) })
})

// ── Search Highlight ──

function highlightMatch(text: string, query: string): string {
  if (!query) return text
  const regex = new RegExp(`(${query})`, 'gi')
  return text.replace(regex, '<mark>$1</mark>')
}

describe('Search Highlight', () => {
  it('wraps match in mark tags', () => {
    expect(highlightMatch('Calibração balança', 'balança')).toBe('Calibração <mark>balança</mark>')
  })

  it('handles no match', () => {
    expect(highlightMatch('Calibração', 'xyz')).toBe('Calibração')
  })

  it('handles empty query', () => {
    expect(highlightMatch('Calibração', '')).toBe('Calibração')
  })

  it('is case insensitive', () => {
    expect(highlightMatch('CALIBRAÇÃO Balança', 'calibração')).toBe('<mark>CALIBRAÇÃO</mark> Balança')
  })
})

// ── Number Formatters ──

describe('Number Formatters', () => {
  const formatCurrency = (v: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

  const formatPercent = (v: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 1 }).format(v / 100)

  const formatCompact = (v: number) =>
    new Intl.NumberFormat('pt-BR', { notation: 'compact' }).format(v)

  it('formats BRL currency', () => { expect(formatCurrency(1500.5)).toBe('R$\u00a01.500,50') })
  it('formats zero', () => { expect(formatCurrency(0)).toBe('R$\u00a00,00') })
  it('formats negative', () => { expect(formatCurrency(-500)).toContain('500') })
  it('formats percentage', () => { expect(formatPercent(75.5)).toContain('75') })
  it('formats compact large number', () => {
    const r = formatCompact(1500000)
    expect(r.length).toBeLessThan(10)
  })
})
