import { describe, it, expect } from 'vitest'

// ── Equipment Capacity & Interval Helpers ──

function formatCapacity(value: number, unit: string = 'kg'): string {
  if (value >= 1000 && unit === 'kg') return `${value / 1000} t`
  if (value >= 1000 && unit === 'g') return `${value / 1000} kg`
  return `${value} ${unit}`
}

function getCalibrationDueLabel(daysUntilDue: number): string {
  if (daysUntilDue < 0) return `Vencido há ${Math.abs(daysUntilDue)} dias`
  if (daysUntilDue === 0) return 'Vence hoje'
  if (daysUntilDue <= 30) return `Vence em ${daysUntilDue} dias`
  if (daysUntilDue <= 90) return `Vence em ${Math.round(daysUntilDue / 30)} meses`
  return `Vence em ${Math.round(daysUntilDue / 30)} meses`
}

describe('Format Capacity', () => {
  it('simple kg', () => expect(formatCapacity(500, 'kg')).toBe('500 kg'))
  it('kg to ton', () => expect(formatCapacity(15000, 'kg')).toBe('15 t'))
  it('g to kg', () => expect(formatCapacity(5000, 'g')).toBe('5 kg'))
  it('small g', () => expect(formatCapacity(200, 'g')).toBe('200 g'))
})

describe('Calibration Due Label', () => {
  it('vencido', () => expect(getCalibrationDueLabel(-15)).toContain('Vencido'))
  it('hoje', () => expect(getCalibrationDueLabel(0)).toBe('Vence hoje'))
  it('em 10 dias', () => expect(getCalibrationDueLabel(10)).toContain('10 dias'))
  it('em meses', () => expect(getCalibrationDueLabel(60)).toContain('meses'))
})

// ── Customer Financial Summary ──

interface CustomerFinSummary {
  pendingReceivables: number
  overdueReceivables: number
  totalPaid: number
  creditLimit: number
  riskScore: number
}

function getCustomerCreditHealth(summary: CustomerFinSummary): 'green' | 'yellow' | 'red' {
  const utilization = summary.totalPaid > 0
    ? (summary.pendingReceivables + summary.overdueReceivables) / summary.creditLimit
    : 0
  if (summary.overdueReceivables > 0 && summary.riskScore > 7) return 'red'
  if (utilization >= 0.8) return 'yellow'
  return 'green'
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

function parseCurrency(formatted: string): number {
  return parseFloat(formatted.replace(/[^\d,.-]/g, '').replace('.', '').replace(',', '.'))
}

describe('Customer Credit Health', () => {
  it('green — healthy', () => {
    expect(getCustomerCreditHealth({
      pendingReceivables: 5000, overdueReceivables: 0,
      totalPaid: 50000, creditLimit: 100000, riskScore: 3,
    })).toBe('green')
  })
  it('red — overdue + high risk', () => {
    expect(getCustomerCreditHealth({
      pendingReceivables: 10000, overdueReceivables: 5000,
      totalPaid: 20000, creditLimit: 50000, riskScore: 8,
    })).toBe('red')
  })
  it('yellow — high utilization', () => {
    expect(getCustomerCreditHealth({
      pendingReceivables: 80000, overdueReceivables: 0,
      totalPaid: 10000, creditLimit: 100000, riskScore: 3,
    })).toBe('yellow')
  })
})

describe('Currency Format', () => {
  it('formats R$ 1.234,56', () => expect(formatCurrency(1234.56)).toContain('1.234,56'))
  it('formats zero', () => expect(formatCurrency(0)).toContain('0,00'))
  it('formats negative', () => expect(formatCurrency(-500)).toContain('500'))
})

describe('Currency Parse', () => {
  it('parses R$ 1.234,56', () => expect(parseCurrency('R$ 1.234,56')).toBeCloseTo(1234.56))
  it('parses simple', () => expect(parseCurrency('R$ 100,00')).toBeCloseTo(100))
})

// ── Dashboard Widget Data ──

interface DashboardWidget {
  title: string
  value: number
  previousValue: number
  type: 'currency' | 'count' | 'percentage'
}

function getWidgetTrend(widget: DashboardWidget): { direction: 'up' | 'down' | 'stable'; percentage: number } {
  if (widget.previousValue === 0) return { direction: 'stable', percentage: 0 }
  const change = ((widget.value - widget.previousValue) / widget.previousValue) * 100
  const rounded = Math.round(change * 10) / 10
  if (rounded > 0) return { direction: 'up', percentage: rounded }
  if (rounded < 0) return { direction: 'down', percentage: Math.abs(rounded) }
  return { direction: 'stable', percentage: 0 }
}

function formatWidgetValue(value: number, type: 'currency' | 'count' | 'percentage'): string {
  switch (type) {
    case 'currency': return formatCurrency(value)
    case 'count': return value.toLocaleString('pt-BR')
    case 'percentage': return `${value.toFixed(1)}%`
  }
}

describe('Widget Trend', () => {
  it('up 20%', () => {
    const result = getWidgetTrend({ title: 'Revenue', value: 120000, previousValue: 100000, type: 'currency' })
    expect(result.direction).toBe('up')
    expect(result.percentage).toBe(20)
  })
  it('down 25%', () => {
    const result = getWidgetTrend({ title: 'WOs', value: 75, previousValue: 100, type: 'count' })
    expect(result.direction).toBe('down')
    expect(result.percentage).toBe(25)
  })
  it('stable', () => {
    const result = getWidgetTrend({ title: 'X', value: 100, previousValue: 100, type: 'count' })
    expect(result.direction).toBe('stable')
  })
  it('zero previous → stable', () => {
    const result = getWidgetTrend({ title: 'X', value: 50, previousValue: 0, type: 'count' })
    expect(result.direction).toBe('stable')
  })
})

describe('Format Widget Value', () => {
  it('currency', () => expect(formatWidgetValue(50000, 'currency')).toContain('50.000'))
  it('count', () => expect(formatWidgetValue(1234, 'count')).toContain('1.234'))
  it('percentage', () => expect(formatWidgetValue(78.5, 'percentage')).toBe('78.5%'))
})

// ── Table Export helpers ──

function exportToCsvString(headers: string[], rows: string[][]): string {
  const headerLine = headers.join(';')
  const dataLines = rows.map(row => row.join(';'))
  return [headerLine, ...dataLines].join('\n')
}

describe('CSV Export', () => {
  it('simple export', () => {
    const csv = exportToCsvString(['Nome', 'Valor'], [['Teste', '100'], ['Outro', '200']])
    expect(csv).toContain('Nome;Valor')
    expect(csv).toContain('Teste;100')
  })
  it('empty rows', () => {
    const csv = exportToCsvString(['A', 'B'], [])
    expect(csv).toBe('A;B')
  })
  it('header count', () => {
    const csv = exportToCsvString(['A', 'B', 'C'], [['1', '2', '3']])
    const lines = csv.split('\n')
    expect(lines).toHaveLength(2)
  })
})

// ── Permission Matrix ──

type Role = 'admin' | 'manager' | 'technician' | 'viewer'

const PERMISSIONS_MATRIX: Record<Role, string[]> = {
  admin: ['*'],
  manager: ['customers.*', 'work-orders.*', 'quotes.*', 'invoices.*', 'reports.view', 'equipments.*'],
  technician: ['work-orders.view', 'work-orders.edit', 'equipments.view', 'equipments.edit'],
  viewer: ['customers.view', 'work-orders.view', 'equipments.view', 'reports.view'],
}

function hasPermission(role: Role, permission: string): boolean {
  const perms = PERMISSIONS_MATRIX[role]
  if (perms.includes('*')) return true
  const [module, action] = permission.split('.')
  return perms.includes(permission) || perms.includes(`${module}.*`)
}

describe('Permission Matrix', () => {
  it('admin has everything', () => expect(hasPermission('admin', 'anything.here')).toBe(true))
  it('manager can view reports', () => expect(hasPermission('manager', 'reports.view')).toBe(true))
  it('manager can manage customers', () => expect(hasPermission('manager', 'customers.delete')).toBe(true))
  it('technician can edit WO', () => expect(hasPermission('technician', 'work-orders.edit')).toBe(true))
  it('technician cannot delete WO', () => expect(hasPermission('technician', 'work-orders.delete')).toBe(false))
  it('viewer can only view', () => expect(hasPermission('viewer', 'customers.view')).toBe(true))
  it('viewer cannot edit', () => expect(hasPermission('viewer', 'customers.edit')).toBe(false))
})
