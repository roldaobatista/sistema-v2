import { describe, it, expect } from 'vitest'

// ── Brazilian Document Validators (real logic from backend) ──

function sanitizeCPF(cpf: string): string {
  return cpf.replace(/\D/g, '')
}

function isValidCPF(cpf: string): boolean {
  const numbers = sanitizeCPF(cpf)
  if (numbers.length !== 11) return false
  if (/^(\d)\1{10}$/.test(numbers)) return false

  let sum = 0
  for (let i = 0; i < 9; i++) {
    sum += parseInt(numbers.charAt(i)) * (10 - i)
  }
  let check = 11 - (sum % 11)
  if (check >= 10) check = 0
  if (parseInt(numbers.charAt(9)) !== check) return false

  sum = 0
  for (let i = 0; i < 10; i++) {
    sum += parseInt(numbers.charAt(i)) * (11 - i)
  }
  check = 11 - (sum % 11)
  if (check >= 10) check = 0
  return parseInt(numbers.charAt(10)) === check
}

function sanitizeCNPJ(cnpj: string): string {
  return cnpj.replace(/\D/g, '')
}

function isValidCNPJ(cnpj: string): boolean {
  const numbers = sanitizeCNPJ(cnpj)
  if (numbers.length !== 14) return false
  if (/^(\d)\1{13}$/.test(numbers)) return false

  const weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
  const weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]

  let sum = 0
  for (let i = 0; i < 12; i++) {
    sum += parseInt(numbers.charAt(i)) * weights1[i]
  }
  let check = sum % 11 < 2 ? 0 : 11 - (sum % 11)
  if (parseInt(numbers.charAt(12)) !== check) return false

  sum = 0
  for (let i = 0; i < 13; i++) {
    sum += parseInt(numbers.charAt(i)) * weights2[i]
  }
  check = sum % 11 < 2 ? 0 : 11 - (sum % 11)
  return parseInt(numbers.charAt(13)) === check
}

function formatCPF(cpf: string): string {
  const n = sanitizeCPF(cpf)
  if (n.length !== 11) return cpf
  return `${n.slice(0, 3)}.${n.slice(3, 6)}.${n.slice(6, 9)}-${n.slice(9)}`
}

function formatCNPJ(cnpj: string): string {
  const n = sanitizeCNPJ(cnpj)
  if (n.length !== 14) return cnpj
  return `${n.slice(0, 2)}.${n.slice(2, 5)}.${n.slice(5, 8)}/${n.slice(8, 12)}-${n.slice(12)}`
}

function formatPhone(phone: string): string {
  const n = phone.replace(/\D/g, '')
  if (n.length === 11) return `(${n.slice(0, 2)}) ${n.slice(2, 7)}-${n.slice(7)}`
  if (n.length === 10) return `(${n.slice(0, 2)}) ${n.slice(2, 6)}-${n.slice(6)}`
  return phone
}

function formatCEP(cep: string): string {
  const n = cep.replace(/\D/g, '')
  if (n.length !== 8) return cep
  return `${n.slice(0, 5)}-${n.slice(5)}`
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

// ── CPF Validation ──

describe('CPF Validation', () => {
  it('valid CPF 529.982.247-25', () => expect(isValidCPF('529.982.247-25')).toBe(true))
  it('valid CPF raw 52998224725', () => expect(isValidCPF('52998224725')).toBe(true))
  it('invalid CPF wrong check digit', () => expect(isValidCPF('529.982.247-99')).toBe(false))
  it('invalid CPF all same digit', () => expect(isValidCPF('111.111.111-11')).toBe(false))
  it('invalid CPF too short', () => expect(isValidCPF('123456')).toBe(false))
  it('invalid CPF too long', () => expect(isValidCPF('123456789012')).toBe(false))
  it('invalid CPF empty', () => expect(isValidCPF('')).toBe(false))
  it('valid CPF 123.456.789-09', () => expect(isValidCPF('12345678909')).toBe(true))
  it('all same digits rejected (000)', () => expect(isValidCPF('00000000000')).toBe(false))
  it('all same digits rejected (999)', () => expect(isValidCPF('99999999999')).toBe(false))
})

// ── CNPJ Validation ──

describe('CNPJ Validation', () => {
  it('valid CNPJ 11.222.333/0001-81', () => expect(isValidCNPJ('11.222.333/0001-81')).toBe(true))
  it('valid CNPJ raw 11222333000181', () => expect(isValidCNPJ('11222333000181')).toBe(true))
  it('invalid CNPJ wrong check', () => expect(isValidCNPJ('11.222.333/0001-99')).toBe(false))
  it('invalid CNPJ all same', () => expect(isValidCNPJ('11111111111111')).toBe(false))
  it('invalid CNPJ too short', () => expect(isValidCNPJ('1234')).toBe(false))
  it('invalid CNPJ empty', () => expect(isValidCNPJ('')).toBe(false))
})

// ── Formatters ──

describe('CPF Formatter', () => {
  it('formats 52998224725 → 529.982.247-25', () => expect(formatCPF('52998224725')).toBe('529.982.247-25'))
  it('formats already formatted', () => expect(formatCPF('529.982.247-25')).toBe('529.982.247-25'))
  it('returns short input as-is', () => expect(formatCPF('123')).toBe('123'))
})

describe('CNPJ Formatter', () => {
  it('formats 11222333000181 → 11.222.333/0001-81', () => expect(formatCNPJ('11222333000181')).toBe('11.222.333/0001-81'))
  it('returns short input as-is', () => expect(formatCNPJ('123')).toBe('123'))
})

describe('Phone Formatter', () => {
  it('formats 11 digits (mobile)', () => expect(formatPhone('11987654321')).toBe('(11) 98765-4321'))
  it('formats 10 digits (landline)', () => expect(formatPhone('1132165498')).toBe('(11) 3216-5498'))
  it('returns short as-is', () => expect(formatPhone('123')).toBe('123'))
})

describe('CEP Formatter', () => {
  it('formats 01310100 → 01310-100', () => expect(formatCEP('01310100')).toBe('01310-100'))
  it('formats with dash', () => expect(formatCEP('01310-100')).toBe('01310-100'))
  it('returns short as-is', () => expect(formatCEP('123')).toBe('123'))
})

describe('Currency Formatter', () => {
  it('formats 1000 → R$ 1.000,00', () => {
    const result = formatCurrency(1000)
    expect(result).toContain('1.000')
    expect(result).toContain('R$')
  })
  it('formats 0 → R$ 0,00', () => {
    const result = formatCurrency(0)
    expect(result).toContain('0,00')
  })
  it('formats 99999.99', () => {
    const result = formatCurrency(99999.99)
    expect(result).toContain('99.999')
  })
  it('formats negative', () => {
    const result = formatCurrency(-500)
    expect(result).toContain('500')
  })
})

// ── Equipment Categories and Statuses (matching backend) ──

const EQUIPMENT_CATEGORIES: Record<string, string> = {
  balanca_analitica: 'Balança Analítica',
  balanca_semi_analitica: 'Balança Semi-Analítica',
  balanca_plataforma: 'Balança de Plataforma',
  balanca_rodoviaria: 'Balança Rodoviária',
  balanca_contadora: 'Balança Contadora',
  balanca_precisao: 'Balança de Precisão',
  massa_padrao: 'Massa Padrão',
  termometro: 'Termômetro',
  paquimetro: 'Paquímetro',
  micrometro: 'Micrômetro',
  manometro: 'Manômetro',
  outro: 'Outro',
}

const PRECISION_CLASSES: Record<string, string> = {
  I: 'Classe I (Especial)',
  II: 'Classe II (Fina)',
  III: 'Classe III (Média)',
  IIII: 'Classe IIII (Ordinária)',
}

describe('Equipment Categories', () => {
  it('has 12 categories', () => expect(Object.keys(EQUIPMENT_CATEGORIES)).toHaveLength(12))
  it('includes balanca_analitica', () => expect(EQUIPMENT_CATEGORIES.balanca_analitica).toBe('Balança Analítica'))
  it('includes termometro', () => expect(EQUIPMENT_CATEGORIES.termometro).toBe('Termômetro'))
  it('includes outro', () => expect(EQUIPMENT_CATEGORIES.outro).toBe('Outro'))
})

describe('Precision Classes', () => {
  it('has 4 classes', () => expect(Object.keys(PRECISION_CLASSES)).toHaveLength(4))
  it('class I = Especial', () => expect(PRECISION_CLASSES.I).toBe('Classe I (Especial)'))
  it('class IIII = Ordinária', () => expect(PRECISION_CLASSES.IIII).toBe('Classe IIII (Ordinária)'))
})

// ── Date helpers ──

function isOverdue(dueDate: Date | null): boolean {
  if (!dueDate) return false
  return dueDate < new Date()
}

function daysUntilDue(dueDate: Date | null): number | null {
  if (!dueDate) return null
  const diff = dueDate.getTime() - new Date().getTime()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
}

describe('Date helpers', () => {
  it('past date is overdue', () => {
    const past = new Date()
    past.setDate(past.getDate() - 5)
    expect(isOverdue(past)).toBe(true)
  })
  it('future date is NOT overdue', () => {
    const future = new Date()
    future.setDate(future.getDate() + 5)
    expect(isOverdue(future)).toBe(false)
  })
  it('null is NOT overdue', () => expect(isOverdue(null)).toBe(false))
  it('daysUntilDue null returns null', () => expect(daysUntilDue(null)).toBeNull())
  it('daysUntilDue future returns positive', () => {
    const future = new Date()
    future.setDate(future.getDate() + 10)
    const days = daysUntilDue(future)!
    expect(days).toBeGreaterThan(0)
    expect(days).toBeLessThanOrEqual(11)
  })
  it('daysUntilDue past returns negative', () => {
    const past = new Date()
    past.setDate(past.getDate() - 10)
    expect(daysUntilDue(past)!).toBeLessThan(0)
  })
})
