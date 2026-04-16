import { describe, it, expect, vi } from 'vitest'

// ── Tenant Context ──

interface TenantContext {
  id: number
  name: string
  slug: string
  settings: Record<string, string>
}

function createTenantContext(data: Partial<TenantContext> = {}): TenantContext {
  return { id: 1, name: 'KALIBRIUM', slug: 'kalibrium', settings: {}, ...data }
}

describe('Tenant Context', () => {
  it('creates default context', () => {
    const ctx = createTenantContext()
    expect(ctx.id).toBe(1)
    expect(ctx.name).toBe('KALIBRIUM')
  })

  it('overrides values', () => {
    const ctx = createTenantContext({ name: 'Custom' })
    expect(ctx.name).toBe('Custom')
  })

  it('preserves settings', () => {
    const ctx = createTenantContext({ settings: { timezone: 'UTC' } })
    expect(ctx.settings.timezone).toBe('UTC')
  })
})

// ── API Response Handler ──

interface ApiResponse<T> { data: T; meta?: { total: number; page: number } }
interface ApiError { status: number; message: string; errors?: Record<string, string[]> }

function isApiError(response: unknown): response is ApiError {
  return typeof response === 'object' && response !== null && 'status' in response && 'message' in response
}

function extractValidationErrors(error: ApiError): string[] {
  if (!error.errors) return [error.message]
  return Object.values(error.errors).flat()
}

function getFirstError(error: ApiError): string {
  const errors = extractValidationErrors(error)
  return errors[0] || 'Erro desconhecido'
}

describe('API Response Handler', () => {
  it('detects API error', () => {
    const err: ApiError = { status: 422, message: 'Validation failed', errors: { name: ['Required'] } }
    expect(isApiError(err)).toBe(true)
  })

  it('rejects non-error objects', () => {
    expect(isApiError({ data: [] })).toBe(false)
  })

  it('extracts validation errors', () => {
    const err: ApiError = { status: 422, message: 'fail', errors: { name: ['Required'], email: ['Invalid'] } }
    expect(extractValidationErrors(err)).toEqual(['Required', 'Invalid'])
  })

  it('returns message when no errors', () => {
    const err: ApiError = { status: 500, message: 'Server error' }
    expect(extractValidationErrors(err)).toEqual(['Server error'])
  })

  it('gets first error', () => {
    const err: ApiError = { status: 422, message: 'fail', errors: { name: ['Nome obrigatório'] } }
    expect(getFirstError(err)).toBe('Nome obrigatório')
  })
})

// ── Table Column Config ──

interface ColumnConfig {
  key: string
  label: string
  sortable?: boolean
  width?: string
  render?: (value: unknown) => string
}

function buildTableColumns(configs: ColumnConfig[]): ColumnConfig[] {
  return configs.map(c => ({ sortable: false, ...c }))
}

describe('Table Column Config', () => {
  it('builds columns with defaults', () => {
    const cols = buildTableColumns([
      { key: 'name', label: 'Nome' },
      { key: 'email', label: 'Email' },
    ])
    expect(cols).toHaveLength(2)
    expect(cols[0].sortable).toBe(false)
  })

  it('preserves sortable flag', () => {
    const cols = buildTableColumns([{ key: 'name', label: 'Nome', sortable: true }])
    expect(cols[0].sortable).toBe(true)
  })

  it('applies custom width', () => {
    const cols = buildTableColumns([{ key: 'id', label: 'ID', width: '80px' }])
    expect(cols[0].width).toBe('80px')
  })
})

// ── Breadcrumb Builder ──

interface Breadcrumb { label: string; href?: string }

function buildBreadcrumbs(segments: string[]): Breadcrumb[] {
  const map: Record<string, string> = {
    dashboard: 'Dashboard',
    'work-orders': 'Ordens de Serviço',
    customers: 'Clientes',
    equipments: 'Equipamentos',
    quotes: 'Orçamentos',
    financial: 'Financeiro',
    crm: 'CRM',
    settings: 'Configurações',
    create: 'Novo',
    edit: 'Editar',
  }

  return segments.map((seg, i) => ({
    label: map[seg] || seg,
    href: i < segments.length - 1 ? '/' + segments.slice(0, i + 1).join('/') : undefined,
  }))
}

describe('Breadcrumb Builder', () => {
  it('builds dashboard breadcrumb', () => {
    const bc = buildBreadcrumbs(['dashboard'])
    expect(bc[0].label).toBe('Dashboard')
    expect(bc[0].href).toBeUndefined()
  })

  it('builds multi-level breadcrumbs', () => {
    const bc = buildBreadcrumbs(['work-orders', 'create'])
    expect(bc[0].label).toBe('Ordens de Serviço')
    expect(bc[0].href).toBe('/work-orders')
    expect(bc[1].label).toBe('Novo')
    expect(bc[1].href).toBeUndefined()
  })

  it('handles unknown segments', () => {
    const bc = buildBreadcrumbs(['unknown-page'])
    expect(bc[0].label).toBe('unknown-page')
  })
})

// ── Query String Builder ──

function buildQueryString(params: Record<string, unknown>): string {
  const entries = Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')
  if (entries.length === 0) return ''
  return '?' + entries.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`).join('&')
}

describe('Query String Builder', () => {
  it('builds simple query', () => {
    expect(buildQueryString({ page: 1, search: 'test' })).toBe('?page=1&search=test')
  })

  it('filters empty values', () => {
    expect(buildQueryString({ page: 1, search: '' })).toBe('?page=1')
  })

  it('handles null values', () => {
    expect(buildQueryString({ page: null, search: 'test' })).toBe('?search=test')
  })

  it('returns empty for no params', () => {
    expect(buildQueryString({})).toBe('')
  })

  it('encodes special characters', () => {
    const qs = buildQueryString({ search: 'calibração & testes' })
    expect(qs).toContain('calibra')
  })
})

// ── Event Bus Pattern ──

type EventHandler = (...args: unknown[]) => void

class EventBus {
  private handlers: Map<string, EventHandler[]> = new Map()

  on(event: string, handler: EventHandler) {
    if (!this.handlers.has(event)) this.handlers.set(event, [])
    this.handlers.get(event)!.push(handler)
  }

  off(event: string, handler: EventHandler) {
    const handlers = this.handlers.get(event)
    if (handlers) {
      this.handlers.set(event, handlers.filter(h => h !== handler))
    }
  }

  emit(event: string, ...args: unknown[]) {
    this.handlers.get(event)?.forEach(h => h(...args))
  }
}

describe('Event Bus', () => {
  it('registers and emits events', () => {
    const bus = new EventBus()
    const handler = vi.fn()
    bus.on('test', handler)
    bus.emit('test', 'data')
    expect(handler).toHaveBeenCalledWith('data')
  })

  it('unregisters events', () => {
    const bus = new EventBus()
    const handler = vi.fn()
    bus.on('test', handler)
    bus.off('test', handler)
    bus.emit('test')
    expect(handler).not.toHaveBeenCalled()
  })

  it('supports multiple handlers', () => {
    const bus = new EventBus()
    const h1 = vi.fn()
    const h2 = vi.fn()
    bus.on('test', h1)
    bus.on('test', h2)
    bus.emit('test')
    expect(h1).toHaveBeenCalled()
    expect(h2).toHaveBeenCalled()
  })

  it('does not crash on unregistered events', () => {
    const bus = new EventBus()
    expect(() => bus.emit('unknown')).not.toThrow()
  })
})
