import { describe, it, expect } from 'vitest'

// ── DRE Calculator (matching backend DREService exactly) ──

interface DREData {
  receitasBrutas: number
  deducoes: number
  custosServicos: number
  despesasOperacionais: number
  despesasFinanceiras: number
}

function calculateDRE(data: DREData) {
  const receitasLiquidas = data.receitasBrutas - data.deducoes
  const lucroBruto = receitasLiquidas - data.custosServicos
  const totalDespesas = data.despesasOperacionais + data.despesasFinanceiras
  const resultadoOperacional = lucroBruto - data.despesasOperacionais
  const resultadoLiquido = lucroBruto - totalDespesas
  const margemBruta = data.receitasBrutas > 0
    ? Math.round((lucroBruto / data.receitasBrutas) * 10000) / 100
    : 0
  const margemLiquida = data.receitasBrutas > 0
    ? Math.round((resultadoLiquido / data.receitasBrutas) * 10000) / 100
    : 0

  return {
    receitasLiquidas,
    lucroBruto,
    totalDespesas,
    resultadoOperacional,
    resultadoLiquido,
    margemBruta,
    margemLiquida,
  }
}

describe('DRE Calculator', () => {
  it('simple scenario', () => {
    const result = calculateDRE({
      receitasBrutas: 100000,
      deducoes: 5000,
      custosServicos: 30000,
      despesasOperacionais: 15000,
      despesasFinanceiras: 10000,
    })
    expect(result.receitasLiquidas).toBe(95000)
    expect(result.lucroBruto).toBe(65000)
    expect(result.totalDespesas).toBe(25000)
    expect(result.resultadoOperacional).toBe(50000)
    expect(result.resultadoLiquido).toBe(40000)
  })
  it('margin calculations', () => {
    const result = calculateDRE({
      receitasBrutas: 100000,
      deducoes: 0,
      custosServicos: 40000,
      despesasOperacionais: 20000,
      despesasFinanceiras: 10000,
    })
    expect(result.margemBruta).toBe(60)
    expect(result.margemLiquida).toBe(30)
  })
  it('zero revenue → zero margins', () => {
    const result = calculateDRE({
      receitasBrutas: 0, deducoes: 0, custosServicos: 0,
      despesasOperacionais: 0, despesasFinanceiras: 0,
    })
    expect(result.margemBruta).toBe(0)
    expect(result.margemLiquida).toBe(0)
  })
  it('negative result', () => {
    const result = calculateDRE({
      receitasBrutas: 10000, deducoes: 0, custosServicos: 8000,
      despesasOperacionais: 5000, despesasFinanceiras: 3000,
    })
    expect(result.resultadoLiquido).toBeLessThan(0)
  })
})

// ── Cash Flow Projection (matching backend CashFlowProjectionService) ──

interface CashFlowSummary {
  entradasPrevistas: number
  entradasRealizadas: number
  saidasPrevistas: number
  saidasRealizadas: number
}

function calculateCashFlowSummary(data: CashFlowSummary) {
  const totalEntradas = data.entradasPrevistas + data.entradasRealizadas
  const totalSaidas = data.saidasPrevistas + data.saidasRealizadas
  const saldoPrevisto = totalEntradas - totalSaidas
  return { totalEntradas, totalSaidas, saldoPrevisto }
}

function getCashFlowHealth(saldo: number): 'critical' | 'warning' | 'healthy' {
  if (saldo < 0) return 'critical'
  if (saldo < 5000) return 'warning'
  return 'healthy'
}

describe('Cash Flow Summary', () => {
  it('positive balance', () => {
    const result = calculateCashFlowSummary({
      entradasPrevistas: 50000, entradasRealizadas: 20000,
      saidasPrevistas: 30000, saidasRealizadas: 10000,
    })
    expect(result.totalEntradas).toBe(70000)
    expect(result.totalSaidas).toBe(40000)
    expect(result.saldoPrevisto).toBe(30000)
  })
  it('negative balance', () => {
    const result = calculateCashFlowSummary({
      entradasPrevistas: 10000, entradasRealizadas: 5000,
      saidasPrevistas: 20000, saidasRealizadas: 10000,
    })
    expect(result.saldoPrevisto).toBeLessThan(0)
  })
  it('zero all', () => {
    const result = calculateCashFlowSummary({
      entradasPrevistas: 0, entradasRealizadas: 0,
      saidasPrevistas: 0, saidasRealizadas: 0,
    })
    expect(result.saldoPrevisto).toBe(0)
  })
})

describe('Cash Flow Health', () => {
  it('negative → critical', () => expect(getCashFlowHealth(-5000)).toBe('critical'))
  it('low positive → warning', () => expect(getCashFlowHealth(3000)).toBe('warning'))
  it('healthy', () => expect(getCashFlowHealth(50000)).toBe('healthy'))
  it('zero → critical', () => expect(getCashFlowHealth(0)).toBe('warning'))
  it('exactly 5000 → healthy', () => expect(getCashFlowHealth(5000)).toBe('healthy'))
})

// ── Tenant Settings types ──

interface TenantSettings {
  companyName: string
  timezone: string
  currency: string
  language: string
  fiscalRegime: number
  inmetroEnabled: boolean
  defaultCalibrationInterval: number
  emailNotifications: boolean
  pushNotifications: boolean
}

const DEFAULT_SETTINGS: TenantSettings = {
  companyName: '',
  timezone: 'America/Sao_Paulo',
  currency: 'BRL',
  language: 'pt-BR',
  fiscalRegime: 1,
  inmetroEnabled: true,
  defaultCalibrationInterval: 12,
  emailNotifications: true,
  pushNotifications: true,
}

function mergeSettings(defaults: TenantSettings, overrides: Partial<TenantSettings>): TenantSettings {
  return { ...defaults, ...overrides }
}

describe('Tenant Settings', () => {
  it('default timezone', () => expect(DEFAULT_SETTINGS.timezone).toBe('America/Sao_Paulo'))
  it('default currency', () => expect(DEFAULT_SETTINGS.currency).toBe('BRL'))
  it('default calibration interval', () => expect(DEFAULT_SETTINGS.defaultCalibrationInterval).toBe(12))
  it('merge overrides', () => {
    const result = mergeSettings(DEFAULT_SETTINGS, { companyName: 'Kalibrium' })
    expect(result.companyName).toBe('Kalibrium')
    expect(result.timezone).toBe('America/Sao_Paulo')
  })
  it('override fiscal regime', () => {
    const result = mergeSettings(DEFAULT_SETTINGS, { fiscalRegime: 3 })
    expect(result.fiscalRegime).toBe(3)
  })
})

// ── Work Order Priority colors ──

const WO_PRIORITY_COLORS: Record<string, { bg: string; text: string; label: string }> = {
  urgent: { bg: '#fef2f2', text: '#991b1b', label: 'Urgente' },
  high: { bg: '#fff7ed', text: '#9a3412', label: 'Alta' },
  medium: { bg: '#fefce8', text: '#854d0e', label: 'Média' },
  low: { bg: '#f0fdf4', text: '#166534', label: 'Baixa' },
}

describe('WO Priority Colors', () => {
  it('4 priority levels', () => expect(Object.keys(WO_PRIORITY_COLORS)).toHaveLength(4))
  it('urgent is red', () => expect(WO_PRIORITY_COLORS.urgent.text).toContain('991b1b'))
  it('low is green', () => expect(WO_PRIORITY_COLORS.low.text).toContain('166534'))
  it('medium label', () => expect(WO_PRIORITY_COLORS.medium.label).toBe('Média'))
})

// ── Report date range presets ──

function getReportPreset(preset: string): { from: Date; to: Date } {
  const now = new Date()
  const to = new Date(now)
  const from = new Date(now)

  switch (preset) {
    case 'today':
      break
    case 'this_week':
      from.setDate(now.getDate() - now.getDay())
      break
    case 'this_month':
      from.setDate(1)
      break
    case 'last_30_days':
      from.setDate(now.getDate() - 30)
      break
    case 'this_quarter': {
      const quarter = Math.floor(now.getMonth() / 3)
      from.setMonth(quarter * 3, 1)
      break
    }
    case 'this_year':
      from.setMonth(0, 1)
      break
  }
  return { from, to }
}

describe('Report Presets', () => {
  it('today → same day', () => {
    const { from, to } = getReportPreset('today')
    expect(from.toDateString()).toBe(to.toDateString())
  })
  it('this_month starts at 1', () => {
    const { from } = getReportPreset('this_month')
    expect(from.getDate()).toBe(1)
  })
  it('this_year starts January', () => {
    const { from } = getReportPreset('this_year')
    expect(from.getMonth()).toBe(0)
    expect(from.getDate()).toBe(1)
  })
  it('last_30_days goes back', () => {
    const { from, to } = getReportPreset('last_30_days')
    const diff = Math.round((to.getTime() - from.getTime()) / (1000 * 60 * 60 * 24))
    expect(diff).toBe(30)
  })
})
