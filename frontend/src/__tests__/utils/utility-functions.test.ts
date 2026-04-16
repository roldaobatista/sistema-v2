import { describe, it, expect } from 'vitest'

// ── Utility Functions Tests ──

describe('Utility Functions', () => {
  it('formats currency correctly', () => {
    const formatCurrency = (value: number) =>
      new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)

    expect(formatCurrency(1000)).toContain('1.000,00')
    expect(formatCurrency(0)).toContain('0,00')
    expect(formatCurrency(99.99)).toContain('99,99')
  })

  it('formats date correctly', () => {
    const formatDate = (date: string) =>
      new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')

    expect(formatDate('2026-03-16')).toContain('16')
    expect(formatDate('2026-01-01')).toContain('01')
  })

  it('formats phone correctly', () => {
    const formatPhone = (phone: string) => {
      const cleaned = phone.replace(/\D/g, '')
      if (cleaned.length === 11) {
        return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 7)}-${cleaned.slice(7)}`
      }
      return phone
    }

    expect(formatPhone('11999887766')).toBe('(11) 99988-7766')
  })

  it('truncates text correctly', () => {
    const truncate = (text: string, max: number) =>
      text.length > max ? text.slice(0, max) + '...' : text

    expect(truncate('Hello World', 5)).toBe('Hello...')
    expect(truncate('Hi', 5)).toBe('Hi')
  })

  it('generates initials from name', () => {
    const getInitials = (name: string) =>
      name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()

    expect(getInitials('João Silva')).toBe('JS')
    expect(getInitials('Maria')).toBe('M')
    expect(getInitials('Ana Beatriz Costa')).toBe('AB')
  })

  it('validates CPF format', () => {
    const isValidCpfFormat = (cpf: string) => /^\d{11}$/.test(cpf.replace(/\D/g, ''))

    expect(isValidCpfFormat('52998224725')).toBe(true)
    expect(isValidCpfFormat('123')).toBe(false)
    expect(isValidCpfFormat('529.982.247-25')).toBe(true)
  })

  it('validates CNPJ format', () => {
    const isValidCnpjFormat = (cnpj: string) => /^\d{14}$/.test(cnpj.replace(/\D/g, ''))

    expect(isValidCnpjFormat('11222333000181')).toBe(true)
    expect(isValidCnpjFormat('123')).toBe(false)
  })

  it('calculates percentage', () => {
    const calcPercentage = (value: number, total: number) =>
      total === 0 ? 0 : Math.round((value / total) * 100)

    expect(calcPercentage(50, 200)).toBe(25)
    expect(calcPercentage(0, 100)).toBe(0)
    expect(calcPercentage(100, 0)).toBe(0)
  })

  it('sorts array of objects by key', () => {
    const sortBy = <T>(arr: T[], key: keyof T, dir: 'asc' | 'desc' = 'asc') =>
      [...arr].sort((a, b) => {
        if (a[key] < b[key]) return dir === 'asc' ? -1 : 1
        if (a[key] > b[key]) return dir === 'asc' ? 1 : -1
        return 0
      })

    const items = [{ name: 'B' }, { name: 'A' }, { name: 'C' }]
    expect(sortBy(items, 'name')[0].name).toBe('A')
    expect(sortBy(items, 'name', 'desc')[0].name).toBe('C')
  })

  it('groups array by key', () => {
    const groupBy = <T>(arr: T[], key: keyof T) =>
      arr.reduce((acc, item) => {
        const k = String(item[key])
        acc[k] = acc[k] || []
        acc[k].push(item)
        return acc
      }, {} as Record<string, T[]>)

    const items = [
      { status: 'open', id: 1 },
      { status: 'closed', id: 2 },
      { status: 'open', id: 3 },
    ]

    const grouped = groupBy(items, 'status')
    expect(grouped['open']).toHaveLength(2)
    expect(grouped['closed']).toHaveLength(1)
  })

  it('removes duplicates from array', () => {
    const unique = <T>(arr: T[]) => [...new Set(arr)]

    expect(unique([1, 2, 2, 3, 3, 3])).toEqual([1, 2, 3])
    expect(unique(['a', 'b', 'a'])).toEqual(['a', 'b'])
  })

  it('deep clones an object', () => {
    const deepClone = <T>(obj: T): T => JSON.parse(JSON.stringify(obj))

    const original = { a: 1, b: { c: 2 } }
    const clone = deepClone(original)
    clone.b.c = 99

    expect(original.b.c).toBe(2)
  })

  it('checks if object is empty', () => {
    const isEmpty = (obj: Record<string, unknown>) => Object.keys(obj).length === 0

    expect(isEmpty({})).toBe(true)
    expect(isEmpty({ a: 1 })).toBe(false)
  })

  it('capitalizes first letter', () => {
    const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)

    expect(capitalize('hello')).toBe('Hello')
    expect(capitalize('')).toBe('')
  })

  it('calculates days between dates', () => {
    const daysBetween = (start: string, end: string) => {
      const diff = new Date(end).getTime() - new Date(start).getTime()
      return Math.ceil(diff / (1000 * 60 * 60 * 24))
    }

    expect(daysBetween('2026-03-01', '2026-03-16')).toBe(15)
    expect(daysBetween('2026-03-16', '2026-03-16')).toBe(0)
  })
})
