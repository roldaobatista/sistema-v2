import { describe, it, expect } from 'vitest'

// ── NF-e Fiscal helpers (matching backend NFeDataBuilder logic) ──

type FiscalRegime = 1 | 2 | 3 | 4
type CFOPCode = '5933' | '5102' | '5405' | '6102' | '6933'

function mapRegimeTributario(regime: FiscalRegime): string {
  switch (regime) {
    case 1: return '1' // Simples Nacional
    case 4: return '1' // MEI
    case 2: return '3' // Lucro Presumido
    case 3: return '3' // Lucro Real
    default: return '1'
  }
}

function isSimpleNacional(regime: FiscalRegime): boolean {
  return regime === 1 || regime === 4
}

function isConsumerFinal(documentLength: number, stateRegistration: string | null): boolean {
  if (documentLength === 11) return true // CPF
  if (!stateRegistration || stateRegistration.toLowerCase() === 'isento') return true
  return false
}

function calculateItemTotal(quantity: number, unitPrice: number, discount: number = 0): number {
  return Math.round((quantity * unitPrice - discount) * 100) / 100
}

function calculateNFeTotal(items: { quantity: number; unit_price: number; discount?: number }[]): number {
  return items.reduce((sum, item) => {
    return sum + calculateItemTotal(item.quantity, item.unit_price, item.discount ?? 0)
  }, 0)
}

function getICMSCSOSN(isSN: boolean): string {
  return isSN ? '102' : '00'
}

function getPISST(isSN: boolean): string {
  return isSN ? '99' : '07'
}

function getCOFINSST(isSN: boolean): string {
  return isSN ? '99' : '07'
}

describe('Fiscal Regime Mapping', () => {
  it('SN=1 → "1"', () => expect(mapRegimeTributario(1)).toBe('1'))
  it('MEI=4 → "1"', () => expect(mapRegimeTributario(4)).toBe('1'))
  it('LP=2 → "3"', () => expect(mapRegimeTributario(2)).toBe('3'))
  it('LR=3 → "3"', () => expect(mapRegimeTributario(3)).toBe('3'))
})

describe('Simples Nacional check', () => {
  it('regime 1 is SN', () => expect(isSimpleNacional(1)).toBe(true))
  it('regime 4 (MEI) is SN', () => expect(isSimpleNacional(4)).toBe(true))
  it('regime 2 is NOT SN', () => expect(isSimpleNacional(2)).toBe(false))
  it('regime 3 is NOT SN', () => expect(isSimpleNacional(3)).toBe(false))
})

describe('Consumer Final', () => {
  it('CPF (11 digits) → consumer final', () => expect(isConsumerFinal(11, null)).toBe(true))
  it('CNPJ without IE → consumer final', () => expect(isConsumerFinal(14, null)).toBe(true))
  it('CNPJ with ISENTO → consumer final', () => expect(isConsumerFinal(14, 'ISENTO')).toBe(true))
  it('CNPJ with IE → NOT consumer final', () => expect(isConsumerFinal(14, '123456789')).toBe(false))
  it('CNPJ with isento lowercase → consumer final', () => expect(isConsumerFinal(14, 'isento')).toBe(true))
})

describe('Item Total', () => {
  it('2 × R$500 = R$1000', () => expect(calculateItemTotal(2, 500)).toBe(1000))
  it('1 × R$1000 - R$100 = R$900', () => expect(calculateItemTotal(1, 1000, 100)).toBe(900))
  it('3 × R$333.33 = R$999.99', () => expect(calculateItemTotal(3, 333.33)).toBe(999.99))
  it('0.5 × R$200 = R$100', () => expect(calculateItemTotal(0.5, 200)).toBe(100))
})

describe('NF-e Total', () => {
  it('single item', () => {
    expect(calculateNFeTotal([{ quantity: 1, unit_price: 1000 }])).toBe(1000)
  })
  it('multiple items', () => {
    expect(calculateNFeTotal([
      { quantity: 2, unit_price: 300 },
      { quantity: 1, unit_price: 400 },
    ])).toBe(1000)
  })
  it('with discount', () => {
    expect(calculateNFeTotal([
      { quantity: 1, unit_price: 1000, discount: 100 },
    ])).toBe(900)
  })
})

describe('ICMS CSOSN', () => {
  it('SN → 102', () => expect(getICMSCSOSN(true)).toBe('102'))
  it('non-SN → 00', () => expect(getICMSCSOSN(false)).toBe('00'))
})

describe('PIS/COFINS ST', () => {
  it('PIS SN → 99', () => expect(getPISST(true)).toBe('99'))
  it('PIS non-SN → 07', () => expect(getPISST(false)).toBe('07'))
  it('COFINS SN → 99', () => expect(getCOFINSST(true)).toBe('99'))
  it('COFINS non-SN → 07', () => expect(getCOFINSST(false)).toBe('07'))
})

// ── CNPJ Normalizer (matching BrasilApiService) ──

interface NormalizedCompany {
  source: string
  cnpj: string
  name: string
  tradeName: string | null
  email: string | null
  phone: string | null
  addressCity: string | null
  addressState: string | null
  partners: { name: string; role: string }[]
}

function normalizeCNPJ(raw: Record<string, unknown>, source: string): NormalizedCompany {
  return {
    source,
    cnpj: (raw.cnpj as string) ?? '',
    name: (raw.razao_social as string) ?? '',
    tradeName: (raw.nome_fantasia as string) ?? null,
    email: (raw.email as string) ?? null,
    phone: (raw.ddd_telefone_1 as string) ?? null,
    addressCity: (raw.municipio as string) ?? null,
    addressState: (raw.uf as string) ?? null,
    partners: ((raw.qsa as unknown[]) ?? []).map((p: Record<string, string>) => ({
      name: p.nome_socio ?? '',
      role: p.qualificacao_socio ?? '',
    })),
  }
}

describe('CNPJ Normalizer', () => {
  it('normalizes BrasilAPI response', () => {
    const result = normalizeCNPJ({
      cnpj: '12345678000190',
      razao_social: 'Empresa Test',
      nome_fantasia: 'Fantasia',
      email: 'test@email.com',
      municipio: 'São Paulo',
      uf: 'SP',
      qsa: [{ nome_socio: 'João', qualificacao_socio: 'Admin' }],
    }, 'brasilapi')

    expect(result.source).toBe('brasilapi')
    expect(result.cnpj).toBe('12345678000190')
    expect(result.name).toBe('Empresa Test')
    expect(result.tradeName).toBe('Fantasia')
    expect(result.partners).toHaveLength(1)
    expect(result.partners[0].name).toBe('João')
  })
  it('handles empty response', () => {
    const result = normalizeCNPJ({}, 'brasilapi')
    expect(result.cnpj).toBe('')
    expect(result.partners).toHaveLength(0)
  })
})

// ── Fiscal validation helpers ──

function validateCNPJ(cnpj: string): boolean {
  const clean = cnpj.replace(/\D/g, '')
  if (clean.length !== 14) return false
  if (/^(\d)\1{13}$/.test(clean)) return false

  const calc = (str: string, weights: number[]) => {
    let sum = 0
    for (let i = 0; i < weights.length; i++) sum += parseInt(str[i]) * weights[i]
    const rem = sum % 11
    return rem < 2 ? 0 : 11 - rem
  }

  const w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const d1 = calc(clean, w1)
  const d2 = calc(clean, w2)
  return parseInt(clean[12]) === d1 && parseInt(clean[13]) === d2
}

function validateAccessKey(key: string): boolean {
  return /^\d{44}$/.test(key)
}

function formatAccessKey(key: string): string {
  return key.replace(/(\d{4})/g, '$1 ').trim()
}

describe('CNPJ Validation (fiscal)', () => {
  it('valid CNPJ', () => expect(validateCNPJ('11222333000181')).toBe(true))
  it('invalid CNPJ', () => expect(validateCNPJ('11222333000100')).toBe(false))
  it('all same digits', () => expect(validateCNPJ('11111111111111')).toBe(false))
  it('short CNPJ', () => expect(validateCNPJ('1234567')).toBe(false))
  it('formatted CNPJ', () => expect(validateCNPJ('11.222.333/0001-81')).toBe(true))
})

describe('Access Key Validation', () => {
  it('valid 44 digits', () => expect(validateAccessKey('12345678901234567890123456789012345678901234')).toBe(true))
  it('too short', () => expect(validateAccessKey('123456')).toBe(false))
  it('with letters', () => expect(validateAccessKey('1234567890123456789012345678901234567890123A')).toBe(false))
})

describe('Access Key Formatting', () => {
  it('groups by 4', () => {
    const formatted = formatAccessKey('1234567890123456')
    expect(formatted).toBe('1234 5678 9012 3456')
  })
})
