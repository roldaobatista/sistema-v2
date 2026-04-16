import { describe, it, expect } from 'vitest'

// ── Import/Export Helpers (matching backend import logic) ──

type ImportFieldType = 'string' | 'number' | 'date' | 'boolean' | 'enum'

interface ImportField {
  key: string
  label: string
  type: ImportFieldType
  required: boolean
  example?: string
}

const CUSTOMER_IMPORT_FIELDS: ImportField[] = [
  { key: 'name', label: 'Nome/Razão Social', type: 'string', required: true, example: 'Empresa Teste LTDA' },
  { key: 'document', label: 'CPF/CNPJ', type: 'string', required: true, example: '12.345.678/0001-90' },
  { key: 'email', label: 'E-mail', type: 'string', required: false, example: 'contato@empresa.com' },
  { key: 'phone', label: 'Telefone', type: 'string', required: false, example: '(11) 9999-8888' },
  { key: 'type', label: 'Tipo', type: 'enum', required: true, example: 'company' },
  { key: 'address_street', label: 'Logradouro', type: 'string', required: false },
  { key: 'address_city', label: 'Cidade', type: 'string', required: false },
  { key: 'address_state', label: 'Estado', type: 'string', required: false, example: 'SP' },
  { key: 'address_zip', label: 'CEP', type: 'string', required: false, example: '01310-100' },
]

const EQUIPMENT_IMPORT_FIELDS: ImportField[] = [
  { key: 'description', label: 'Descrição', type: 'string', required: true, example: 'Balança Toledo' },
  { key: 'tag', label: 'Tag/Patrimônio', type: 'string', required: true, example: 'BAL-001' },
  { key: 'serial_number', label: 'Número de Série', type: 'string', required: false },
  { key: 'accuracy_class', label: 'Classe de Exatidão', type: 'enum', required: false, example: 'III' },
  { key: 'max_capacity', label: 'Capacidade Máxima', type: 'number', required: false, example: '30000' },
  { key: 'resolution', label: 'Resolução', type: 'number', required: false, example: '1' },
]

function getRequiredFields(fields: ImportField[]): ImportField[] {
  return fields.filter(f => f.required)
}

function validateImportRow(row: Record<string, unknown>, fields: ImportField[]): string[] {
  const errors: string[] = []
  for (const field of fields) {
    if (field.required && (row[field.key] === undefined || row[field.key] === null || row[field.key] === '')) {
      errors.push(`${field.label} é obrigatório`)
    }
  }
  return errors
}

function generateCsvTemplate(fields: ImportField[]): string {
  return fields.map(f => f.label).join(';')
}

describe('Customer Import Fields', () => {
  it('has 9 fields', () => expect(CUSTOMER_IMPORT_FIELDS).toHaveLength(9))
  it('name is required', () => expect(CUSTOMER_IMPORT_FIELDS.find(f => f.key === 'name')?.required).toBe(true))
  it('email is optional', () => expect(CUSTOMER_IMPORT_FIELDS.find(f => f.key === 'email')?.required).toBe(false))
  it('3 required fields', () => expect(getRequiredFields(CUSTOMER_IMPORT_FIELDS)).toHaveLength(3))
})

describe('Equipment Import Fields', () => {
  it('has 6 fields', () => expect(EQUIPMENT_IMPORT_FIELDS).toHaveLength(6))
  it('description required', () => expect(EQUIPMENT_IMPORT_FIELDS.find(f => f.key === 'description')?.required).toBe(true))
  it('2 required fields', () => expect(getRequiredFields(EQUIPMENT_IMPORT_FIELDS)).toHaveLength(2))
})

describe('Validate Import Row', () => {
  it('valid row → 0 errors', () => {
    const row = { name: 'Test', document: '123', type: 'company' }
    expect(validateImportRow(row, CUSTOMER_IMPORT_FIELDS)).toHaveLength(0)
  })
  it('missing name → 1 error', () => {
    const row = { document: '123', type: 'company' }
    const errors = validateImportRow(row, CUSTOMER_IMPORT_FIELDS)
    expect(errors).toHaveLength(1)
    expect(errors[0]).toContain('Nome')
  })
  it('missing all required → 3 errors', () => {
    expect(validateImportRow({}, CUSTOMER_IMPORT_FIELDS)).toHaveLength(3)
  })
  it('empty string counts as missing', () => {
    const row = { name: '', document: '123', type: 'company' }
    expect(validateImportRow(row, CUSTOMER_IMPORT_FIELDS)).toHaveLength(1)
  })
})

describe('CSV Template', () => {
  it('customer template', () => {
    const tpl = generateCsvTemplate(CUSTOMER_IMPORT_FIELDS)
    expect(tpl).toContain('Nome/Razão Social')
    expect(tpl).toContain('CPF/CNPJ')
  })
  it('equipment template', () => {
    const tpl = generateCsvTemplate(EQUIPMENT_IMPORT_FIELDS)
    expect(tpl).toContain('Descrição')
    expect(tpl).toContain('Tag/Patrimônio')
  })
})

// ── Tenant onboarding checklist ──

interface OnboardingStep {
  id: string
  label: string
  completed: boolean
  required: boolean
}

function getOnboardingProgress(steps: OnboardingStep[]): number {
  const required = steps.filter(s => s.required)
  if (required.length === 0) return 100
  const completed = required.filter(s => s.completed)
  return Math.round((completed.length / required.length) * 100)
}

function getNextStep(steps: OnboardingStep[]): OnboardingStep | null {
  return steps.find(s => !s.completed && s.required) ?? null
}

const onboardingSteps: OnboardingStep[] = [
  { id: 'company', label: 'Dados da empresa', completed: true, required: true },
  { id: 'users', label: 'Cadastrar usuários', completed: true, required: true },
  { id: 'customers', label: 'Importar clientes', completed: false, required: true },
  { id: 'equipments', label: 'Cadastrar equipamentos', completed: false, required: true },
  { id: 'logo', label: 'Upload do logo', completed: false, required: false },
  { id: 'fiscal', label: 'Configurar fiscal', completed: false, required: false },
]

describe('Onboarding Progress', () => {
  it('50% — 2/4 required completed', () => expect(getOnboardingProgress(onboardingSteps)).toBe(50))
  it('100% — all required done', () => {
    const allDone = onboardingSteps.map(s => ({ ...s, completed: true }))
    expect(getOnboardingProgress(allDone)).toBe(100)
  })
  it('0% — none done', () => {
    const noneDone = onboardingSteps.map(s => ({ ...s, completed: false }))
    expect(getOnboardingProgress(noneDone)).toBe(0)
  })
})

describe('Next Onboarding Step', () => {
  it('next is customers', () => expect(getNextStep(onboardingSteps)?.id).toBe('customers'))
  it('all done → null', () => {
    const allDone = onboardingSteps.map(s => ({ ...s, completed: true }))
    expect(getNextStep(allDone)).toBeNull()
  })
})

// ── Phone/Document Formatters (Brazilian) ──

function formatPhone(phone: string): string {
  const clean = phone.replace(/\D/g, '')
  if (clean.length === 11) return `(${clean.slice(0, 2)}) ${clean.slice(2, 7)}-${clean.slice(7)}`
  if (clean.length === 10) return `(${clean.slice(0, 2)}) ${clean.slice(2, 6)}-${clean.slice(6)}`
  return clean
}

function formatCPF(cpf: string): string {
  const clean = cpf.replace(/\D/g, '')
  if (clean.length !== 11) return clean
  return `${clean.slice(0, 3)}.${clean.slice(3, 6)}.${clean.slice(6, 9)}-${clean.slice(9)}`
}

function formatCNPJ(cnpj: string): string {
  const clean = cnpj.replace(/\D/g, '')
  if (clean.length !== 14) return clean
  return `${clean.slice(0, 2)}.${clean.slice(2, 5)}.${clean.slice(5, 8)}/${clean.slice(8, 12)}-${clean.slice(12)}`
}

function formatCEP(cep: string): string {
  const clean = cep.replace(/\D/g, '')
  if (clean.length !== 8) return clean
  return `${clean.slice(0, 5)}-${clean.slice(5)}`
}

describe('Phone Format', () => {
  it('mobile 11 digits', () => expect(formatPhone('11999998888')).toBe('(11) 99999-8888'))
  it('landline 10 digits', () => expect(formatPhone('1133334444')).toBe('(11) 3333-4444'))
  it('short → raw', () => expect(formatPhone('1234')).toBe('1234'))
})

describe('CPF Format', () => {
  it('formats CPF', () => expect(formatCPF('12345678901')).toBe('123.456.789-01'))
  it('short → raw', () => expect(formatCPF('123')).toBe('123'))
})

describe('CNPJ Format', () => {
  it('formats CNPJ', () => expect(formatCNPJ('12345678000190')).toBe('12.345.678/0001-90'))
  it('short → raw', () => expect(formatCNPJ('123')).toBe('123'))
})

describe('CEP Format', () => {
  it('formats CEP', () => expect(formatCEP('01310100')).toBe('01310-100'))
  it('short → raw', () => expect(formatCEP('123')).toBe('123'))
})

// ── Relative Time (matching frontend display) ──

function formatRelativeTime(date: Date): string {
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  const diffHrs = Math.floor(diffMin / 60)
  const diffDays = Math.floor(diffHrs / 24)

  if (diffMin < 1) return 'agora'
  if (diffMin < 60) return `há ${diffMin}min`
  if (diffHrs < 24) return `há ${diffHrs}h`
  if (diffDays === 1) return 'ontem'
  if (diffDays < 30) return `há ${diffDays} dias`
  if (diffDays < 365) return `há ${Math.floor(diffDays / 30)} meses`
  return `há ${Math.floor(diffDays / 365)} anos`
}

describe('Relative Time', () => {
  it('agora', () => expect(formatRelativeTime(new Date())).toBe('agora'))
  it('yesterday', () => {
    const yesterday = new Date(); yesterday.setDate(yesterday.getDate() - 1)
    yesterday.setHours(yesterday.getHours() - 1) // ensure > 24h
    expect(formatRelativeTime(yesterday)).toBe('ontem')
  })
  it('days ago', () => {
    const fiveDays = new Date(); fiveDays.setDate(fiveDays.getDate() - 5)
    expect(formatRelativeTime(fiveDays)).toContain('5 dias')
  })
  it('months ago', () => {
    const twoMonths = new Date(); twoMonths.setMonth(twoMonths.getMonth() - 2)
    twoMonths.setDate(twoMonths.getDate() - 1)
    expect(formatRelativeTime(twoMonths)).toContain('meses')
  })
})
