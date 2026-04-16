import { describe, it, expect } from 'vitest'

// ── API Response Helpers (matching real app patterns) ──

interface ApiResponse<T> {
  data: T
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

function isApiError(response: unknown): response is { message: string } {
  return typeof response === 'object' && response !== null && 'message' in response
}

function getFirstError(errors: Record<string, string[]>): string {
  const firstKey = Object.keys(errors)[0]
  return errors[firstKey]?.[0] ?? 'Erro desconhecido'
}

function formatApiErrors(errors: Record<string, string[]>): string[] {
  return Object.entries(errors).flatMap(([_, msgs]) => msgs)
}

describe('API Response Helpers', () => {
  it('isApiError true', () => expect(isApiError({ message: 'Error' })).toBe(true))
  it('isApiError false null', () => expect(isApiError(null)).toBe(false))
  it('isApiError false string', () => expect(isApiError('error')).toBe(false))
  it('getFirstError', () => {
    expect(getFirstError({ email: ['Email inválido', 'Email obrigatório'] })).toBe('Email inválido')
  })
  it('getFirstError empty', () => {
    expect(getFirstError({})).toBe('Erro desconhecido')
  })
  it('formatApiErrors', () => {
    const errors = { email: ['Email inválido'], name: ['Nome obrigatório', 'Nome curto'] }
    expect(formatApiErrors(errors)).toHaveLength(3)
  })
})

// ── Route matching helpers ──

function isActiveRoute(currentPath: string, linkPath: string): boolean {
  if (linkPath === '/') return currentPath === '/'
  return currentPath.startsWith(linkPath)
}

function getModuleFromPath(path: string): string {
  const segments = path.split('/').filter(Boolean)
  return segments[0] || 'dashboard'
}

const MODULE_ICONS: Record<string, string> = {
  dashboard: 'LayoutDashboard',
  customers: 'Users',
  'work-orders': 'Wrench',
  equipments: 'Scale',
  quotes: 'FileText',
  invoices: 'Receipt',
  crm: 'Target',
  settings: 'Settings',
  reports: 'BarChart3',
}

describe('Route Matching', () => {
  it('exact root match', () => expect(isActiveRoute('/', '/')).toBe(true))
  it('root not active for /customers', () => expect(isActiveRoute('/customers', '/')).toBe(false))
  it('/customers active for /customers/123', () => expect(isActiveRoute('/customers/123', '/customers')).toBe(true))
  it('/customers not active for /work-orders', () => expect(isActiveRoute('/work-orders', '/customers')).toBe(false))
})

describe('Module from Path', () => {
  it('/customers → customers', () => expect(getModuleFromPath('/customers')).toBe('customers'))
  it('/crm/deals → crm', () => expect(getModuleFromPath('/crm/deals')).toBe('crm'))
  it('/ → dashboard', () => expect(getModuleFromPath('/')).toBe('dashboard'))
})

describe('Module Icons', () => {
  it('has dashboard icon', () => expect(MODULE_ICONS.dashboard).toBe('LayoutDashboard'))
  it('has customers icon', () => expect(MODULE_ICONS.customers).toBe('Users'))
  it('has all 9 modules', () => expect(Object.keys(MODULE_ICONS)).toHaveLength(9))
})

// ── Access Control helpers ──

type ModuleKey =
  | 'customers' | 'work_orders' | 'equipments' | 'quotes'
  | 'invoices' | 'finance' | 'crm' | 'settings' | 'hr'

interface ModuleAccess {
  module: ModuleKey
  canView: boolean
  canCreate: boolean
  canEdit: boolean
  canDelete: boolean
}

function hasModuleAccess(modules: ModuleAccess[], module: ModuleKey): boolean {
  const found = modules.find(m => m.module === module)
  return found?.canView ?? false
}

function canPerformAction(
  modules: ModuleAccess[],
  module: ModuleKey,
  action: 'view' | 'create' | 'edit' | 'delete'
): boolean {
  const found = modules.find(m => m.module === module)
  if (!found) return false
  const map = { view: found.canView, create: found.canCreate, edit: found.canEdit, delete: found.canDelete }
  return map[action]
}

describe('Module Access', () => {
  const accesses: ModuleAccess[] = [
    { module: 'customers', canView: true, canCreate: true, canEdit: true, canDelete: false },
    { module: 'settings', canView: false, canCreate: false, canEdit: false, canDelete: false },
  ]

  it('has access to customers', () => expect(hasModuleAccess(accesses, 'customers')).toBe(true))
  it('no access to settings', () => expect(hasModuleAccess(accesses, 'settings')).toBe(false))
  it('no access to unknown', () => expect(hasModuleAccess(accesses, 'hr')).toBe(false))
  it('can create customers', () => expect(canPerformAction(accesses, 'customers', 'create')).toBe(true))
  it('cannot delete customers', () => expect(canPerformAction(accesses, 'customers', 'delete')).toBe(false))
  it('cannot edit settings', () => expect(canPerformAction(accesses, 'settings', 'edit')).toBe(false))
})

// ── Notification count badge ──

function formatNotificationCount(count: number): string {
  if (count === 0) return ''
  if (count > 99) return '99+'
  return String(count)
}

describe('Notification Badge', () => {
  it('0 → empty', () => expect(formatNotificationCount(0)).toBe(''))
  it('5 → "5"', () => expect(formatNotificationCount(5)).toBe('5'))
  it('99 → "99"', () => expect(formatNotificationCount(99)).toBe('99'))
  it('100 → "99+"', () => expect(formatNotificationCount(100)).toBe('99+'))
  it('999 → "99+"', () => expect(formatNotificationCount(999)).toBe('99+'))
})

// ── Deep clone utility ──

function deepClone<T>(obj: T): T {
  return JSON.parse(JSON.stringify(obj))
}

function mergeDefaults<T extends Record<string, unknown>>(defaults: T, overrides: Partial<T>): T {
  return { ...defaults, ...overrides }
}

describe('Deep Clone', () => {
  it('clones object', () => {
    const orig = { a: 1, b: { c: 2 } }
    const cloned = deepClone(orig)
    cloned.b.c = 99
    expect(orig.b.c).toBe(2)
  })
  it('clones array', () => {
    const orig = [1, 2, 3]
    const cloned = deepClone(orig)
    cloned.push(4)
    expect(orig).toHaveLength(3)
  })
})

describe('Merge Defaults', () => {
  it('merges overrides', () => {
    const result = mergeDefaults({ a: 1, b: 2 }, { b: 99 })
    expect(result).toEqual({ a: 1, b: 99 })
  })
  it('keeps defaults', () => {
    const result = mergeDefaults({ a: 1, b: 2 }, {})
    expect(result).toEqual({ a: 1, b: 2 })
  })
})

// ── Keyboard shortcut parser ──

function parseShortcut(shortcut: string): { ctrl: boolean; alt: boolean; shift: boolean; key: string } {
  const parts = shortcut.toLowerCase().split('+').map(s => s.trim())
  return {
    ctrl: parts.includes('ctrl'),
    alt: parts.includes('alt'),
    shift: parts.includes('shift'),
    key: parts.filter(p => !['ctrl', 'alt', 'shift'].includes(p))[0] ?? '',
  }
}

describe('Keyboard Shortcut Parser', () => {
  it('Ctrl+S', () => {
    const result = parseShortcut('Ctrl+S')
    expect(result.ctrl).toBe(true)
    expect(result.key).toBe('s')
  })
  it('Ctrl+Shift+N', () => {
    const result = parseShortcut('Ctrl+Shift+N')
    expect(result.ctrl).toBe(true)
    expect(result.shift).toBe(true)
    expect(result.key).toBe('n')
  })
  it('Alt+F4', () => {
    const result = parseShortcut('Alt+F4')
    expect(result.alt).toBe(true)
    expect(result.key).toBe('f4')
  })
})
