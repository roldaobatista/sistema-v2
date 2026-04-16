import { describe, it, expect } from 'vitest'

// ── Date Utilities ──

function formatDateBR(date: string): string {
  return new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')
}

function formatDateTimeBR(date: string): string {
  return new Date(date).toLocaleString('pt-BR')
}

function isOverdue(dateStr: string): boolean {
  return new Date(dateStr) < new Date()
}

function daysBetween(start: string, end: string): number {
  const d = new Date(end).getTime() - new Date(start).getTime()
  return Math.ceil(d / (1000 * 60 * 60 * 24))
}

function addDays(date: string, days: number): string {
  const d = new Date(date + 'T00:00:00')
  d.setDate(d.getDate() + days)
  return d.toISOString().split('T')[0]
}

function startOfMonth(date: string): string {
  const d = new Date(date + 'T00:00:00')
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
}

function endOfMonth(date: string): string {
  const d = new Date(date + 'T00:00:00')
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]
}

function getRelativeTime(date: string): string {
  const diff = Date.now() - new Date(date).getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 60) return `${minutes}min atrás`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h atrás`
  const days = Math.floor(hours / 24)
  return `${days}d atrás`
}

describe('Date Utilities', () => {
  it('formats date to BR format', () => {
    expect(formatDateBR('2026-03-16')).toContain('16')
  })

  it('formats datetime to BR format', () => {
    const result = formatDateTimeBR('2026-03-16T14:30:00')
    expect(result).toContain('16')
  })

  it('detects overdue date', () => {
    expect(isOverdue('2020-01-01')).toBe(true)
  })

  it('detects future date as not overdue', () => {
    expect(isOverdue('2030-12-31')).toBe(false)
  })

  it('calculates days between', () => {
    expect(daysBetween('2026-03-01', '2026-03-16')).toBe(15)
  })

  it('adds days to date', () => {
    expect(addDays('2026-03-16', 5)).toBe('2026-03-21')
  })

  it('gets start of month', () => {
    expect(startOfMonth('2026-03-16')).toBe('2026-03-01')
  })

  it('gets end of month', () => {
    expect(endOfMonth('2026-03-16')).toBe('2026-03-31')
  })

  it('gets end of February', () => {
    expect(endOfMonth('2026-02-15')).toBe('2026-02-28')
  })

  it('gets relative time minutes', () => {
    const fiveMinAgo = new Date(Date.now() - 5 * 60000).toISOString()
    expect(getRelativeTime(fiveMinAgo)).toContain('min')
  })
})

// ── String Utilities ──

function slugify(text: string): string {
  return text
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function maskCpf(cpf: string): string {
  const c = cpf.replace(/\D/g, '')
  return `${c.slice(0, 3)}.${c.slice(3, 6)}.${c.slice(6, 9)}-${c.slice(9, 11)}`
}

function maskCnpj(cnpj: string): string {
  const c = cnpj.replace(/\D/g, '')
  return `${c.slice(0, 2)}.${c.slice(2, 5)}.${c.slice(5, 8)}/${c.slice(8, 12)}-${c.slice(12, 14)}`
}

function maskPhone(phone: string): string {
  const c = phone.replace(/\D/g, '')
  if (c.length === 11) return `(${c.slice(0, 2)}) ${c.slice(2, 7)}-${c.slice(7)}`
  if (c.length === 10) return `(${c.slice(0, 2)}) ${c.slice(2, 6)}-${c.slice(6)}`
  return phone
}

function maskCep(cep: string): string {
  const c = cep.replace(/\D/g, '')
  return `${c.slice(0, 5)}-${c.slice(5, 8)}`
}

function pluralize(count: number, singular: string, plural: string): string {
  return count === 1 ? `${count} ${singular}` : `${count} ${plural}`
}

function bytesToHuman(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
}

describe('String Utilities', () => {
  it('slugifies text', () => {
    expect(slugify('Ordem de Serviço')).toBe('ordem-de-servico')
  })

  it('slugifies with special characters', () => {
    expect(slugify('Ação & Reação!')).toBe('acao-reacao')
  })

  it('masks CPF', () => {
    expect(maskCpf('52998224725')).toBe('529.982.247-25')
  })

  it('masks CNPJ', () => {
    expect(maskCnpj('11222333000181')).toBe('11.222.333/0001-81')
  })

  it('masks phone 11 digits', () => {
    expect(maskPhone('11999887766')).toBe('(11) 99988-7766')
  })

  it('masks phone 10 digits', () => {
    expect(maskPhone('1133224455')).toBe('(11) 3322-4455')
  })

  it('masks CEP', () => {
    expect(maskCep('01310100')).toBe('01310-100')
  })

  it('pluralizes correctly 1 item', () => {
    expect(pluralize(1, 'item', 'itens')).toBe('1 item')
  })

  it('pluralizes correctly multiple items', () => {
    expect(pluralize(5, 'item', 'itens')).toBe('5 itens')
  })

  it('converts bytes to human readable', () => {
    expect(bytesToHuman(500)).toBe('500 B')
    expect(bytesToHuman(2048)).toBe('2.0 KB')
    expect(bytesToHuman(1048576)).toBe('1.0 MB')
  })
})

// ── Array Utilities ──

function chunkArray<T>(arr: T[], size: number): T[][] {
  const r: T[][] = []
  for (let i = 0; i < arr.length; i += size) r.push(arr.slice(i, i + size))
  return r
}

function flattenDeep(arr: unknown[]): unknown[] {
  return arr.reduce<unknown[]>((acc, item) =>
    Array.isArray(item) ? acc.concat(flattenDeep(item)) : acc.concat(item), [])
}

function intersect<T>(a: T[], b: T[]): T[] {
  const setB = new Set(b)
  return a.filter(x => setB.has(x))
}

function difference<T>(a: T[], b: T[]): T[] {
  const setB = new Set(b)
  return a.filter(x => !setB.has(x))
}

describe('Array Utilities', () => {
  it('chunks array', () => {
    expect(chunkArray([1, 2, 3, 4, 5], 2)).toEqual([[1, 2], [3, 4], [5]])
  })

  it('chunks empty array', () => {
    expect(chunkArray([], 3)).toEqual([])
  })

  it('flattens deep array', () => {
    expect(flattenDeep([1, [2, [3, [4]]]])).toEqual([1, 2, 3, 4])
  })

  it('intersects arrays', () => {
    expect(intersect([1, 2, 3], [2, 3, 4])).toEqual([2, 3])
  })

  it('finds difference', () => {
    expect(difference([1, 2, 3], [2, 3, 4])).toEqual([1])
  })

  it('empty intersection', () => {
    expect(intersect([1, 2], [3, 4])).toEqual([])
  })
})

// ── Color Utilities ──

function statusColor(status: string): string {
  const map: Record<string, string> = {
    open: 'blue', in_progress: 'yellow', completed: 'green',
    cancelled: 'gray', overdue: 'red', pending: 'orange',
  }
  return map[status] || 'gray'
}

function priorityColor(priority: string): string {
  const map: Record<string, string> = {
    low: 'gray', medium: 'blue', high: 'orange', urgent: 'red',
  }
  return map[priority] || 'gray'
}

describe('Color Utilities', () => {
  it('returns blue for open', () => { expect(statusColor('open')).toBe('blue') })
  it('returns green for completed', () => { expect(statusColor('completed')).toBe('green') })
  it('returns red for overdue', () => { expect(statusColor('overdue')).toBe('red') })
  it('returns gray for unknown', () => { expect(statusColor('unknown')).toBe('gray') })
  it('returns red for urgent priority', () => { expect(priorityColor('urgent')).toBe('red') })
  it('returns gray for low priority', () => { expect(priorityColor('low')).toBe('gray') })
})
