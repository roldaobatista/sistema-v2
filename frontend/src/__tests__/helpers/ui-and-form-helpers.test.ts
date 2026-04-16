import { describe, it, expect } from 'vitest'

// â”€â”€ Table/Grid Column helpers (matching real app datatable logic) â”€â”€

interface Column<T = unknown> {
  key: string
  label: string
  sortable?: boolean
  visible?: boolean
  width?: string
  format?: (value: unknown, row: T) => string
}

function getVisibleColumns<T>(columns: Column<T>[]): Column<T>[] {
  return columns.filter(c => c.visible !== false)
}

function getSortableColumns<T>(columns: Column<T>[]): Column<T>[] {
  return columns.filter(c => c.sortable === true)
}

const customerColumns: Column[] = [
  { key: 'id', label: '#', sortable: true, visible: true, width: '60px' },
  { key: 'name', label: 'Nome', sortable: true, visible: true },
  { key: 'document', label: 'CPF/CNPJ', sortable: false, visible: true },
  { key: 'email', label: 'E-mail', sortable: true, visible: true },
  { key: 'phone', label: 'Telefone', sortable: false, visible: true },
  { key: 'city', label: 'Cidade', sortable: true, visible: true },
  { key: 'internal_notes', label: 'Notas', sortable: false, visible: false },
  { key: 'created_at', label: 'Criado em', sortable: true, visible: false },
]

describe('Column Helpers', () => {
  it('getVisibleColumns filters hidden', () => {
    expect(getVisibleColumns(customerColumns)).toHaveLength(6)
  })
  it('getSortableColumns filters non-sortable', () => {
    expect(getSortableColumns(customerColumns)).toHaveLength(5)
  })
  it('all visible columns have labels', () => {
    getVisibleColumns(customerColumns).forEach(c => expect(c.label).toBeTruthy())
  })
})

// â”€â”€ Breadcrumb Builder â”€â”€

interface Breadcrumb { label: string; path?: string }

function buildBreadcrumbs(path: string): Breadcrumb[] {
  const crumbs: Breadcrumb[] = [{ label: 'Dashboard', path: '/' }]
  const map: Record<string, string> = {
    customers: 'Clientes',
    'work-orders': 'Ordens de Serviço',
    equipments: 'Equipamentos',
    quotes: 'Orçamentos',
    invoices: 'Faturas',
    settings: 'Configurações',
    crm: 'CRM',
    deals: 'Negócios',
    reports: 'Relatórios',
  }
  const parts = path.split('/').filter(Boolean)
  let current = ''
  for (const part of parts) {
    current += `/${part}`
    const label = map[part] || part
    crumbs.push({ label, path: current })
  }
  return crumbs
}

describe('Breadcrumb Builder', () => {
  it('root is Dashboard', () => {
    expect(buildBreadcrumbs('/')[0].label).toBe('Dashboard')
  })
  it('/customers → Dashboard > Clientes', () => {
    const crumbs = buildBreadcrumbs('/customers')
    expect(crumbs).toHaveLength(2)
    expect(crumbs[1].label).toBe('Clientes')
  })
  it('/crm/deals → Dashboard > CRM > Negócios', () => {
    const crumbs = buildBreadcrumbs('/crm/deals')
    expect(crumbs).toHaveLength(3)
    expect(crumbs[1].label).toBe('CRM')
    expect(crumbs[2].label).toBe('Negócios')
  })
  it('unknown part uses raw string', () => {
    const crumbs = buildBreadcrumbs('/unknown')
    expect(crumbs[1].label).toBe('unknown')
  })
})

// â”€â”€ Color palette helpers (matching design system) â”€â”€

const STATUS_COLORS: Record<string, string> = {
  success: '#10b981',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#3b82f6',
  default: '#6b7280',
  teal: '#0d9488',
}

function getStatusBgClass(color: string): string {
  const map: Record<string, string> = {
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-amber-100 text-amber-800',
    danger: 'bg-red-100 text-red-800',
    info: 'bg-blue-100 text-blue-800',
    default: 'bg-gray-100 text-gray-800',
    teal: 'bg-teal-100 text-teal-800',
  }
  return map[color] || map.default
}

describe('Status Colors', () => {
  it('has 6 colors', () => expect(Object.keys(STATUS_COLORS)).toHaveLength(6))
  it('success is green', () => expect(STATUS_COLORS.success).toContain('10b981'))
  it('danger is red', () => expect(STATUS_COLORS.danger).toContain('ef4444'))
})

describe('Status BG Class', () => {
  it('success → bg-emerald', () => expect(getStatusBgClass('success')).toContain('emerald'))
  it('danger → bg-red', () => expect(getStatusBgClass('danger')).toContain('red'))
  it('unknown → default', () => expect(getStatusBgClass('xyz')).toContain('gray'))
})

// â”€â”€ Form Validation helpers â”€â”€

function validateRequired(value: unknown, fieldName: string): string | null {
  if (value === null || value === undefined || value === '') {
    return `${fieldName} é obrigatório`
  }
  return null
}

function validateEmail(email: string): string | null {
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'E-mail inválido'
  return null
}

function validateMinLength(value: string, min: number): string | null {
  if (value.length < min) return `Mínimo ${min} caracteres`
  return null
}

function validateMaxLength(value: string, max: number): string | null {
  if (value.length > max) return `Máximo ${max} caracteres`
  return null
}

function validateNumericRange(value: number, min: number, max: number): string | null {
  if (value < min) return `Valor mínimo: ${min}`
  if (value > max) return `Valor máximo: ${max}`
  return null
}

describe('Form Validation - Required', () => {
  it('null → error', () => expect(validateRequired(null, 'Nome')).toBe('Nome é obrigatório'))
  it('empty string → error', () => expect(validateRequired('', 'Email')).toBe('Email é obrigatório'))
  it('valid → null', () => expect(validateRequired('test', 'Nome')).toBeNull())
  it('0 is valid', () => expect(validateRequired(0, 'Qtd')).toBeNull())
})

describe('Form Validation - Email', () => {
  it('valid email', () => expect(validateEmail('user@example.com')).toBeNull())
  it('missing @', () => expect(validateEmail('invalid')).toBe('E-mail inválido'))
  it('missing domain', () => expect(validateEmail('user@')).toBe('E-mail inválido'))
  it('spaces', () => expect(validateEmail('user @example.com')).toBe('E-mail inválido'))
})

describe('Form Validation - MinLength', () => {
  it('too short', () => expect(validateMinLength('ab', 3)).toBe('Mínimo 3 caracteres'))
  it('exact', () => expect(validateMinLength('abc', 3)).toBeNull())
  it('longer', () => expect(validateMinLength('abcd', 3)).toBeNull())
})

describe('Form Validation - MaxLength', () => {
  it('too long', () => expect(validateMaxLength('abcdef', 5)).toBe('Máximo 5 caracteres'))
  it('exact', () => expect(validateMaxLength('abcde', 5)).toBeNull())
  it('shorter', () => expect(validateMaxLength('abc', 5)).toBeNull())
})

describe('Form Validation - Numeric Range', () => {
  it('below min', () => expect(validateNumericRange(-1, 0, 100)).toBe('Valor mínimo: 0'))
  it('above max', () => expect(validateNumericRange(101, 0, 100)).toBe('Valor máximo: 100'))
  it('valid', () => expect(validateNumericRange(50, 0, 100)).toBeNull())
  it('at min', () => expect(validateNumericRange(0, 0, 100)).toBeNull())
  it('at max', () => expect(validateNumericRange(100, 0, 100)).toBeNull())
})

// â”€â”€ Debounce test (functional) â”€â”€

function slugify(text: string): string {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

describe('Slugify', () => {
  it('simple text', () => expect(slugify('Hello World')).toBe('hello-world'))
  it('accented text', () => expect(slugify('Calibração Técnica')).toBe('calibracao-tecnica'))
  it('special chars', () => expect(slugify('Price: $100!')).toBe('price-100'))
  it('multiple spaces', () => expect(slugify('a   b   c')).toBe('a-b-c'))
  it('empty', () => expect(slugify('')).toBe(''))
})
