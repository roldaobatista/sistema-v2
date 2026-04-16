import { describe, it, expect } from 'vitest'

// ── Pagination Helper (matching real app logic) ──

interface PaginationMeta {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

function getPageRange(meta: PaginationMeta): number[] {
  const pages: number[] = []
  const start = Math.max(1, meta.currentPage - 2)
  const end = Math.min(meta.lastPage, meta.currentPage + 2)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
}

function hasNextPage(meta: PaginationMeta): boolean {
  return meta.currentPage < meta.lastPage
}

function hasPrevPage(meta: PaginationMeta): boolean {
  return meta.currentPage > 1
}

function getTotalPages(total: number, perPage: number): number {
  return Math.ceil(total / perPage)
}

describe('Pagination Helpers', () => {
  it('getPageRange returns correct range', () => {
    expect(getPageRange({ currentPage: 5, lastPage: 10, perPage: 10, total: 100 }))
      .toEqual([3, 4, 5, 6, 7])
  })
  it('getPageRange capped at 1', () => {
    expect(getPageRange({ currentPage: 1, lastPage: 10, perPage: 10, total: 100 }))
      .toEqual([1, 2, 3])
  })
  it('getPageRange capped at lastPage', () => {
    expect(getPageRange({ currentPage: 10, lastPage: 10, perPage: 10, total: 100 }))
      .toEqual([8, 9, 10])
  })
  it('hasNextPage true', () => expect(hasNextPage({ currentPage: 1, lastPage: 5, perPage: 10, total: 50 })).toBe(true))
  it('hasNextPage false at last', () => expect(hasNextPage({ currentPage: 5, lastPage: 5, perPage: 10, total: 50 })).toBe(false))
  it('hasPrevPage false at first', () => expect(hasPrevPage({ currentPage: 1, lastPage: 5, perPage: 10, total: 50 })).toBe(false))
  it('hasPrevPage true', () => expect(hasPrevPage({ currentPage: 3, lastPage: 5, perPage: 10, total: 50 })).toBe(true))
  it('getTotalPages 100/10 = 10', () => expect(getTotalPages(100, 10)).toBe(10))
  it('getTotalPages 101/10 = 11', () => expect(getTotalPages(101, 10)).toBe(11))
  it('getTotalPages 0/10 = 0', () => expect(getTotalPages(0, 10)).toBe(0))
})

// ── Search/Filter helpers ──

function debounce<T extends (...args: unknown[]) => void>(fn: T, ms: number): T {
  let timer: ReturnType<typeof setTimeout>
  return ((...args: unknown[]) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), ms)
  }) as T
}

function normalizeStr(s: string): string {
  return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
}

function filterBySearch<T extends Record<string, unknown>>(
  items: T[],
  query: string,
  fields: (keyof T)[]
): T[] {
  if (!query.trim()) return items
  const normalized = normalizeStr(query)
  return items.filter(item =>
    fields.some(f => normalizeStr(String(item[f] ?? '')).includes(normalized))
  )
}

function sortBy<T>(items: T[], key: keyof T, direction: 'asc' | 'desc' = 'asc'): T[] {
  return [...items].sort((a, b) => {
    const va = a[key], vb = b[key]
    if (va === vb) return 0
    const cmp = va! > vb! ? 1 : -1
    return direction === 'asc' ? cmp : -cmp
  })
}

describe('filterBySearch', () => {
  const items = [
    { id: 1, name: 'Padaria Central', city: 'São Paulo' },
    { id: 2, name: 'Supermercado Bom Dia', city: 'Campinas' },
    { id: 3, name: 'Farmácia Saúde', city: 'São Paulo' },
  ]

  it('returns all for empty query', () => expect(filterBySearch(items, '', ['name'])).toHaveLength(3))
  it('filters by name', () => expect(filterBySearch(items, 'padaria', ['name'])).toHaveLength(1))
  it('filters by city', () => expect(filterBySearch(items, 'são paulo', ['city'])).toHaveLength(2))
  it('filters multiple fields', () => expect(filterBySearch(items, 'central', ['name', 'city'])).toHaveLength(1))
  it('case insensitive', () => expect(filterBySearch(items, 'FARMACIA', ['name'])).toHaveLength(1))
  it('no match', () => expect(filterBySearch(items, 'xyz', ['name'])).toHaveLength(0))
})

describe('sortBy', () => {
  const items = [
    { id: 3, name: 'C' },
    { id: 1, name: 'A' },
    { id: 2, name: 'B' },
  ]

  it('sorts asc by name', () => {
    const sorted = sortBy(items, 'name', 'asc')
    expect(sorted[0].name).toBe('A')
    expect(sorted[2].name).toBe('C')
  })
  it('sorts desc by name', () => {
    const sorted = sortBy(items, 'name', 'desc')
    expect(sorted[0].name).toBe('C')
  })
  it('sorts by id', () => {
    const sorted = sortBy(items, 'id', 'asc')
    expect(sorted[0].id).toBe(1)
  })
})

// ── Permission helpers ──

type Permission =
  | 'customers.view' | 'customers.create' | 'customers.edit' | 'customers.delete'
  | 'work_orders.view' | 'work_orders.create' | 'work_orders.edit'
  | 'quotes.view' | 'quotes.create' | 'quotes.edit'
  | 'finance.view' | 'finance.create' | 'finance.edit'
  | 'settings.view' | 'settings.edit'

function hasPermission(userPermissions: Permission[], required: Permission): boolean {
  return userPermissions.includes(required)
}

function hasAnyPermission(userPermissions: Permission[], required: Permission[]): boolean {
  return required.some(p => userPermissions.includes(p))
}

function hasAllPermissions(userPermissions: Permission[], required: Permission[]): boolean {
  return required.every(p => userPermissions.includes(p))
}

describe('Permission Helpers', () => {
  const perms: Permission[] = ['customers.view', 'customers.create', 'work_orders.view']

  it('hasPermission true', () => expect(hasPermission(perms, 'customers.view')).toBe(true))
  it('hasPermission false', () => expect(hasPermission(perms, 'settings.edit')).toBe(false))
  it('hasAnyPermission true', () => expect(hasAnyPermission(perms, ['settings.edit', 'customers.view'])).toBe(true))
  it('hasAnyPermission false', () => expect(hasAnyPermission(perms, ['settings.edit', 'finance.view'])).toBe(false))
  it('hasAllPermissions true', () => expect(hasAllPermissions(perms, ['customers.view', 'customers.create'])).toBe(true))
  it('hasAllPermissions false', () => expect(hasAllPermissions(perms, ['customers.view', 'settings.edit'])).toBe(false))
})

// ── Number formatting ──

function formatDecimal(value: number, decimals: number = 2): string {
  return value.toFixed(decimals)
}

function formatPercentage(value: number): string {
  return `${(value * 100).toFixed(1)}%`
}

function parseMoneyInput(input: string): number {
  const clean = input
    .replace(/[^\d,.-]/g, '')  // remove non-numeric chars except ,.­
    .replace(/\./g, '')         // remove thousand separators
    .replace(',', '.')          // replace decimal comma with dot
  return parseFloat(clean) || 0
}

describe('Number Formatting', () => {
  it('formatDecimal 1234.5678 → 1234.57', () => expect(formatDecimal(1234.5678)).toBe('1234.57'))
  it('formatDecimal 0 → 0.00', () => expect(formatDecimal(0)).toBe('0.00'))
  it('formatPercentage 0.15 → 15.0%', () => expect(formatPercentage(0.15)).toBe('15.0%'))
  it('formatPercentage 1 → 100.0%', () => expect(formatPercentage(1)).toBe('100.0%'))
  it('parseMoneyInput R$ 1.234,56 → 1234.56', () => expect(parseMoneyInput('R$ 1.234,56')).toBeCloseTo(1234.56))
  it('parseMoneyInput empty → 0', () => expect(parseMoneyInput('')).toBe(0))
  it('parseMoneyInput invalid → 0', () => expect(parseMoneyInput('abc')).toBe(0))
})

// ── URL builder ──

function buildApiUrl(base: string, params: Record<string, string | number | undefined>): string {
  const url = new URL(base, 'http://localhost')
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      url.searchParams.append(key, String(value))
    }
  })
  return url.pathname + url.search
}

describe('URL Builder', () => {
  it('builds with params', () => {
    const url = buildApiUrl('/api/v1/customers', { search: 'test', page: 2 })
    expect(url).toContain('search=test')
    expect(url).toContain('page=2')
  })
  it('ignores undefined', () => {
    const url = buildApiUrl('/api/v1/customers', { search: undefined, page: 1 })
    expect(url).not.toContain('search')
  })
  it('no params', () => {
    const url = buildApiUrl('/api/v1/customers', {})
    expect(url).toBe('/api/v1/customers')
  })
})

// ── Local Storage helpers ──

function getStoredTheme(): string {
  if (typeof localStorage === 'undefined') return 'light'
  return localStorage.getItem('theme') || 'light'
}

function getStoredSidebarState(): boolean {
  if (typeof localStorage === 'undefined') return true
  return localStorage.getItem('sidebar_collapsed') !== 'true'
}

describe('Storage Helpers', () => {
  it('default theme is light', () => expect(getStoredTheme()).toBe('light'))
  it('default sidebar is expanded', () => expect(getStoredSidebarState()).toBe(true))
})

// ── Truncate text ──

function truncateText(text: string, maxLen: number): string {
  if (text.length <= maxLen) return text
  return text.slice(0, maxLen) + '...'
}

describe('Truncate Text', () => {
  it('short text unchanged', () => expect(truncateText('Hello', 10)).toBe('Hello'))
  it('long text truncated', () => expect(truncateText('Hello World Extended Text', 10)).toBe('Hello Worl...'))
  it('exact length unchanged', () => expect(truncateText('12345', 5)).toBe('12345'))
})
