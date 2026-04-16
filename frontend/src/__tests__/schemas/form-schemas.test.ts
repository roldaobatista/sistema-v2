import { describe, it, expect } from 'vitest'
import { z } from 'zod'

// ── Customer Schemas ──

const customerStoreSchema = z.object({
  name: z.string().min(1, 'Nome é obrigatório'),
  email: z.string().email().optional().or(z.literal('')),
  phone: z.string().optional(),
  type: z.enum(['company', 'individual']),
  document: z.string().optional(),
  address: z.string().optional(),
  city: z.string().optional(),
  state: z.string().optional(),
  zip: z.string().optional(),
})

describe('Customer Store Schema', () => {
  it('passes with valid company data', () => {
    const result = customerStoreSchema.safeParse({
      name: 'Empresa Teste',
      email: 'teste@empresa.com',
      type: 'company',
    })
    expect(result.success).toBe(true)
  })

  it('passes with valid individual data', () => {
    const result = customerStoreSchema.safeParse({
      name: 'João Silva',
      type: 'individual',
      phone: '11999887766',
    })
    expect(result.success).toBe(true)
  })

  it('fails without name', () => {
    const result = customerStoreSchema.safeParse({
      type: 'company',
    })
    expect(result.success).toBe(false)
  })

  it('fails with invalid email', () => {
    const result = customerStoreSchema.safeParse({
      name: 'Teste',
      email: 'not-an-email',
      type: 'company',
    })
    expect(result.success).toBe(false)
  })

  it('fails with invalid type', () => {
    const result = customerStoreSchema.safeParse({
      name: 'Teste',
      type: 'unknown',
    })
    expect(result.success).toBe(false)
  })

  it('allows empty email string', () => {
    const result = customerStoreSchema.safeParse({
      name: 'Teste',
      type: 'company',
      email: '',
    })
    expect(result.success).toBe(true)
  })
})

// ── WorkOrder Schemas ──

const workOrderStoreSchema = z.object({
  customer_id: z.number().int().positive(),
  title: z.string().min(1),
  description: z.string().optional(),
  priority: z.enum(['low', 'medium', 'high', 'urgent']).default('medium'),
  equipment_id: z.number().int().positive().optional(),
  assigned_to: z.number().int().positive().optional(),
  scheduled_date: z.string().optional(),
})

describe('WorkOrder Store Schema', () => {
  it('passes with valid data', () => {
    const result = workOrderStoreSchema.safeParse({
      customer_id: 1,
      title: 'Calibração balança',
      priority: 'high',
    })
    expect(result.success).toBe(true)
  })

  it('fails without customer_id', () => {
    const result = workOrderStoreSchema.safeParse({
      title: 'Sem customer',
    })
    expect(result.success).toBe(false)
  })

  it('fails without title', () => {
    const result = workOrderStoreSchema.safeParse({
      customer_id: 1,
    })
    expect(result.success).toBe(false)
  })

  it('defaults priority to medium', () => {
    const result = workOrderStoreSchema.safeParse({
      customer_id: 1,
      title: 'Test',
    })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.priority).toBe('medium')
    }
  })

  it('rejects invalid priority', () => {
    const result = workOrderStoreSchema.safeParse({
      customer_id: 1,
      title: 'Test',
      priority: 'super-urgent',
    })
    expect(result.success).toBe(false)
  })

  it('rejects negative customer_id', () => {
    const result = workOrderStoreSchema.safeParse({
      customer_id: -1,
      title: 'Test',
    })
    expect(result.success).toBe(false)
  })
})

// ── Quote Schemas ──

const quoteStoreSchema = z.object({
  customer_id: z.number().int().positive(),
  title: z.string().min(1),
  validity_days: z.number().int().positive().default(30),
  items: z.array(z.object({
    description: z.string().min(1),
    quantity: z.number().positive(),
    unit_price: z.number().min(0),
  })).optional(),
})

describe('Quote Store Schema', () => {
  it('passes with valid data', () => {
    const result = quoteStoreSchema.safeParse({
      customer_id: 1,
      title: 'Orçamento calibração',
    })
    expect(result.success).toBe(true)
  })

  it('passes with items', () => {
    const result = quoteStoreSchema.safeParse({
      customer_id: 1,
      title: 'Orçamento',
      items: [
        { description: 'Calibração', quantity: 2, unit_price: 500 },
      ],
    })
    expect(result.success).toBe(true)
  })

  it('fails with empty item description', () => {
    const result = quoteStoreSchema.safeParse({
      customer_id: 1,
      title: 'Orçamento',
      items: [
        { description: '', quantity: 2, unit_price: 500 },
      ],
    })
    expect(result.success).toBe(false)
  })

  it('fails with negative quantity', () => {
    const result = quoteStoreSchema.safeParse({
      customer_id: 1,
      title: 'Orçamento',
      items: [
        { description: 'Item', quantity: -1, unit_price: 500 },
      ],
    })
    expect(result.success).toBe(false)
  })
})

// ── Expense Schema ──

const expenseStoreSchema = z.object({
  description: z.string().min(1),
  amount: z.number().positive(),
  expense_date: z.string().min(1),
  expense_category_id: z.number().int().positive().optional(),
  receipt_url: z.string().url().optional().or(z.literal('')),
})

describe('Expense Store Schema', () => {
  it('passes with valid data', () => {
    const result = expenseStoreSchema.safeParse({
      description: 'Combustível',
      amount: 200,
      expense_date: '2026-03-16',
    })
    expect(result.success).toBe(true)
  })

  it('fails with negative amount', () => {
    const result = expenseStoreSchema.safeParse({
      description: 'Test',
      amount: -50,
      expense_date: '2026-03-16',
    })
    expect(result.success).toBe(false)
  })

  it('fails without description', () => {
    const result = expenseStoreSchema.safeParse({
      amount: 100,
      expense_date: '2026-03-16',
    })
    expect(result.success).toBe(false)
  })
})

// ── AgendaItem Schema ──

const agendaItemSchema = z.object({
  titulo: z.string().min(1, 'Título obrigatório'),
  tipo: z.enum(['tarefa', 'lembrete', 'reuniao', 'evento']),
  prioridade: z.enum(['low', 'medium', 'high', 'urgent']).default('medium'),
  descricao_curta: z.string().optional(),
  due_at: z.string().optional(),
  responsavel_user_id: z.number().int().positive().optional(),
})

describe('AgendaItem Schema', () => {
  it('passes with valid tarefa', () => {
    const result = agendaItemSchema.safeParse({
      titulo: 'Calibrar balança',
      tipo: 'tarefa',
    })
    expect(result.success).toBe(true)
  })

  it('fails without titulo', () => {
    const result = agendaItemSchema.safeParse({
      tipo: 'tarefa',
    })
    expect(result.success).toBe(false)
  })

  it('fails with invalid tipo', () => {
    const result = agendaItemSchema.safeParse({
      titulo: 'Test',
      tipo: 'invalid',
    })
    expect(result.success).toBe(false)
  })

  it('defaults prioridade to medium', () => {
    const result = agendaItemSchema.safeParse({
      titulo: 'Test',
      tipo: 'tarefa',
    })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.prioridade).toBe('medium')
    }
  })
})

// ── CRM Deal Schema ──

const crmDealSchema = z.object({
  customer_id: z.number().int().positive(),
  pipeline_id: z.number().int().positive(),
  stage_id: z.number().int().positive().optional(),
  title: z.string().min(1),
  value: z.number().min(0).default(0),
  probability: z.number().min(0).max(100).default(0),
  expected_close_date: z.string().optional(),
})

describe('CRM Deal Schema', () => {
  it('passes with valid data', () => {
    const result = crmDealSchema.safeParse({
      customer_id: 1,
      pipeline_id: 1,
      title: 'Contrato calibração',
      value: 50000,
    })
    expect(result.success).toBe(true)
  })

  it('fails without pipeline_id', () => {
    const result = crmDealSchema.safeParse({
      customer_id: 1,
      title: 'Test',
    })
    expect(result.success).toBe(false)
  })

  it('rejects probability over 100', () => {
    const result = crmDealSchema.safeParse({
      customer_id: 1,
      pipeline_id: 1,
      title: 'Test',
      probability: 150,
    })
    expect(result.success).toBe(false)
  })

  it('defaults value to 0', () => {
    const result = crmDealSchema.safeParse({
      customer_id: 1,
      pipeline_id: 1,
      title: 'Test',
    })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.value).toBe(0)
    }
  })
})

// ── Login Schema ──

const loginSchema = z.object({
  email: z.string().email('Email inválido'),
  password: z.string().min(6, 'Mínimo 6 caracteres'),
})

describe('Login Schema', () => {
  it('passes with valid credentials', () => {
    const result = loginSchema.safeParse({
      email: 'user@test.com',
      password: '123456',
    })
    expect(result.success).toBe(true)
  })

  it('fails with invalid email', () => {
    const result = loginSchema.safeParse({
      email: 'not-email',
      password: '123456',
    })
    expect(result.success).toBe(false)
  })

  it('fails with short password', () => {
    const result = loginSchema.safeParse({
      email: 'user@test.com',
      password: '12',
    })
    expect(result.success).toBe(false)
  })

  it('fails with empty email', () => {
    const result = loginSchema.safeParse({
      email: '',
      password: '123456',
    })
    expect(result.success).toBe(false)
  })
})
