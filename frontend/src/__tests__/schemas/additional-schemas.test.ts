import { describe, it, expect } from 'vitest'
import { z } from 'zod'

// ── Equipment Schemas ──

const equipmentStoreSchema = z.object({
  name: z.string().min(1, 'Nome obrigatório'),
  customer_id: z.number().int().positive(),
  serial_number: z.string().optional(),
  brand: z.string().optional(),
  model: z.string().optional(),
  capacity: z.string().optional(),
  resolution: z.string().optional(),
  location: z.string().optional(),
  equipment_model_id: z.number().int().positive().optional(),
})

describe('Equipment Store Schema', () => {
  it('passes with valid data', () => {
    const r = equipmentStoreSchema.safeParse({ name: 'Balança', customer_id: 1 })
    expect(r.success).toBe(true)
  })
  it('fails without name', () => {
    const r = equipmentStoreSchema.safeParse({ customer_id: 1 })
    expect(r.success).toBe(false)
  })
  it('fails without customer_id', () => {
    const r = equipmentStoreSchema.safeParse({ name: 'Balança' })
    expect(r.success).toBe(false)
  })
  it('passes with all optional fields', () => {
    const r = equipmentStoreSchema.safeParse({
      name: 'Balança', customer_id: 1,
      serial_number: 'SN-001', brand: 'Toledo', model: 'Prix',
      capacity: '30kg', resolution: '0.005kg',
    })
    expect(r.success).toBe(true)
  })
  it('fails with zero customer_id', () => {
    const r = equipmentStoreSchema.safeParse({ name: 'Balança', customer_id: 0 })
    expect(r.success).toBe(false)
  })
})

// ── Commission Schemas ──

const commissionRuleSchema = z.object({
  name: z.string().min(1),
  type: z.enum(['percentage', 'fixed']),
  percentage: z.number().min(0).max(100).optional(),
  fixed_amount: z.number().min(0).optional(),
  user_id: z.number().int().positive().optional(),
})

describe('Commission Rule Schema', () => {
  it('passes with percentage type', () => {
    const r = commissionRuleSchema.safeParse({ name: 'Vendas', type: 'percentage', percentage: 10 })
    expect(r.success).toBe(true)
  })
  it('passes with fixed type', () => {
    const r = commissionRuleSchema.safeParse({ name: 'Bonus', type: 'fixed', fixed_amount: 500 })
    expect(r.success).toBe(true)
  })
  it('fails without name', () => {
    const r = commissionRuleSchema.safeParse({ type: 'percentage' })
    expect(r.success).toBe(false)
  })
  it('fails with invalid type', () => {
    const r = commissionRuleSchema.safeParse({ name: 'X', type: 'hybrid' })
    expect(r.success).toBe(false)
  })
  it('fails with percentage over 100', () => {
    const r = commissionRuleSchema.safeParse({ name: 'X', type: 'percentage', percentage: 150 })
    expect(r.success).toBe(false)
  })
})

// ── Financial Schemas ──

const accountPayableSchema = z.object({
  description: z.string().min(1),
  amount: z.number().positive(),
  due_date: z.string().min(1),
  category_id: z.number().int().positive().optional(),
  supplier_id: z.number().int().positive().optional(),
  installments: z.number().int().min(1).max(120).default(1),
})

describe('Account Payable Schema', () => {
  it('passes with valid data', () => {
    const r = accountPayableSchema.safeParse({ description: 'Aluguel', amount: 5000, due_date: '2026-04-01' })
    expect(r.success).toBe(true)
  })
  it('fails without description', () => {
    const r = accountPayableSchema.safeParse({ amount: 1000, due_date: '2026-04-01' })
    expect(r.success).toBe(false)
  })
  it('fails with negative amount', () => {
    const r = accountPayableSchema.safeParse({ description: 'X', amount: -100, due_date: '2026-04-01' })
    expect(r.success).toBe(false)
  })
  it('defaults installments to 1', () => {
    const r = accountPayableSchema.safeParse({ description: 'X', amount: 1000, due_date: '2026-04-01' })
    expect(r.success).toBe(true)
    if (r.success) expect(r.data.installments).toBe(1)
  })
  it('rejects over 120 installments', () => {
    const r = accountPayableSchema.safeParse({ description: 'X', amount: 1000, due_date: '2026-04-01', installments: 200 })
    expect(r.success).toBe(false)
  })
})

const accountReceivableSchema = z.object({
  description: z.string().min(1),
  amount: z.number().positive(),
  due_date: z.string().min(1),
  customer_id: z.number().int().positive(),
  installments: z.number().int().min(1).max(120).default(1),
})

describe('Account Receivable Schema', () => {
  it('passes with valid data', () => {
    const r = accountReceivableSchema.safeParse({ description: 'Calibração', amount: 3000, due_date: '2026-04-01', customer_id: 1 })
    expect(r.success).toBe(true)
  })
  it('fails without customer_id', () => {
    const r = accountReceivableSchema.safeParse({ description: 'X', amount: 1000, due_date: '2026-04-01' })
    expect(r.success).toBe(false)
  })
  it('fails with zero amount', () => {
    const r = accountReceivableSchema.safeParse({ description: 'X', amount: 0, due_date: '2026-04-01', customer_id: 1 })
    expect(r.success).toBe(false)
  })
})

// ── ServiceCall Schema ──

const serviceCallSchema = z.object({
  subject: z.string().min(1),
  description: z.string().optional(),
  customer_id: z.number().int().positive(),
  priority: z.enum(['low', 'medium', 'high', 'urgent']).default('medium'),
  sla_policy_id: z.number().int().positive().optional(),
})

describe('ServiceCall Schema', () => {
  it('passes with valid data', () => {
    const r = serviceCallSchema.safeParse({ subject: 'Calibração urgente', customer_id: 1 })
    expect(r.success).toBe(true)
  })
  it('defaults priority to medium', () => {
    const r = serviceCallSchema.safeParse({ subject: 'X', customer_id: 1 })
    expect(r.success).toBe(true)
    if (r.success) expect(r.data.priority).toBe('medium')
  })
  it('fails without subject', () => {
    const r = serviceCallSchema.safeParse({ customer_id: 1 })
    expect(r.success).toBe(false)
  })
  it('fails with invalid priority', () => {
    const r = serviceCallSchema.safeParse({ subject: 'X', customer_id: 1, priority: 'critical' })
    expect(r.success).toBe(false)
  })
})

// ── Product Schema ──

const productSchema = z.object({
  name: z.string().min(1),
  sku: z.string().min(1),
  type: z.enum(['product', 'service']),
  price: z.number().min(0),
  cost: z.number().min(0).optional(),
  description: z.string().optional(),
  min_stock: z.number().int().min(0).default(0),
})

describe('Product Schema', () => {
  it('passes with valid product', () => {
    const r = productSchema.safeParse({ name: 'Peso 1kg', sku: 'PP-001', type: 'product', price: 200 })
    expect(r.success).toBe(true)
  })
  it('passes with service type', () => {
    const r = productSchema.safeParse({ name: 'Calibração', sku: 'SRV-001', type: 'service', price: 500 })
    expect(r.success).toBe(true)
  })
  it('fails without sku', () => {
    const r = productSchema.safeParse({ name: 'Peso', type: 'product', price: 100 })
    expect(r.success).toBe(false)
  })
  it('fails with negative price', () => {
    const r = productSchema.safeParse({ name: 'X', sku: 'X', type: 'product', price: -50 })
    expect(r.success).toBe(false)
  })
  it('fails with invalid type', () => {
    const r = productSchema.safeParse({ name: 'X', sku: 'X', type: 'gadget', price: 100 })
    expect(r.success).toBe(false)
  })
  it('defaults min_stock to 0', () => {
    const r = productSchema.safeParse({ name: 'X', sku: 'X', type: 'product', price: 100 })
    expect(r.success).toBe(true)
    if (r.success) expect(r.data.min_stock).toBe(0)
  })
})

// ── UserProfile Schema ──

const userProfileSchema = z.object({
  name: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  current_password: z.string().min(6).optional(),
  new_password: z.string().min(6).optional(),
  avatar_url: z.string().url().optional().or(z.literal('')),
})

describe('UserProfile Schema', () => {
  it('passes with valid profile', () => {
    const r = userProfileSchema.safeParse({ name: 'João', email: 'joao@k.com' })
    expect(r.success).toBe(true)
  })
  it('fails with invalid email', () => {
    const r = userProfileSchema.safeParse({ name: 'João', email: 'invalid' })
    expect(r.success).toBe(false)
  })
  it('fails with short password', () => {
    const r = userProfileSchema.safeParse({ name: 'João', email: 'j@k.com', new_password: '12' })
    expect(r.success).toBe(false)
  })
  it('allows empty avatar string', () => {
    const r = userProfileSchema.safeParse({ name: 'João', email: 'j@k.com', avatar_url: '' })
    expect(r.success).toBe(true)
  })
})

// ── FundTransfer Schema ──

const fundTransferSchema = z.object({
  from_account_id: z.number().int().positive(),
  to_account_id: z.number().int().positive(),
  amount: z.number().positive(),
  description: z.string().optional(),
}).refine(data => data.from_account_id !== data.to_account_id, {
  message: 'Contas devem ser diferentes',
})

describe('FundTransfer Schema', () => {
  it('passes with valid transfer', () => {
    const r = fundTransferSchema.safeParse({ from_account_id: 1, to_account_id: 2, amount: 1000 })
    expect(r.success).toBe(true)
  })
  it('fails with same accounts', () => {
    const r = fundTransferSchema.safeParse({ from_account_id: 1, to_account_id: 1, amount: 1000 })
    expect(r.success).toBe(false)
  })
  it('fails with zero amount', () => {
    const r = fundTransferSchema.safeParse({ from_account_id: 1, to_account_id: 2, amount: 0 })
    expect(r.success).toBe(false)
  })
  it('fails without from_account_id', () => {
    const r = fundTransferSchema.safeParse({ to_account_id: 2, amount: 500 })
    expect(r.success).toBe(false)
  })
})

// ── InmetroOwner Schema ──

const inmetroOwnerSchema = z.object({
  name: z.string().min(1),
  document: z.string().min(11).max(14),
  state: z.string().length(2),
  city: z.string().optional(),
})

describe('InmetroOwner Schema', () => {
  it('passes with valid owner', () => {
    const r = inmetroOwnerSchema.safeParse({ name: 'Proprietário', document: '12345678000199', state: 'SP' })
    expect(r.success).toBe(true)
  })
  it('fails with short document', () => {
    const r = inmetroOwnerSchema.safeParse({ name: 'X', document: '123', state: 'SP' })
    expect(r.success).toBe(false)
  })
  it('fails with invalid state', () => {
    const r = inmetroOwnerSchema.safeParse({ name: 'X', document: '12345678901', state: 'SAO' })
    expect(r.success).toBe(false)
  })
})
