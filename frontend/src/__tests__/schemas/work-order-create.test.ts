import { describe, it, expect } from 'vitest'
import { workOrderCreateSchema } from '../../lib/work-order-create-schema'

const validMinimal = {
  customer_id: 1,
  description: 'Reparo em balança',
}

const validFull = {
  customer_id: 42,
  description: 'Manutenção preventiva do equipamento de medição',
  priority: 'high' as const,
  service_type: 'preventiva',
  assigned_to: 5,
  seller_id: 3,
  driver_id: 7,
  equipment_id: 10,
  branch_id: 2,
  checklist_id: 4,
  is_warranty: true,
  initial_status: 'in_service' as const,
  discount: '50.00',
  discount_percentage: '10',
  displacement_value: '120.00',
  internal_notes: 'Cliente VIP',
  manual_justification: 'Aprovado pelo gerente',
  os_number: 'OS-2026-001',
  origin_type: 'manual',
  address: 'Rua das Flores, 123',
  city: 'São Paulo',
  state: 'SP',
  tags: ['urgente', 'garantia'],
  received_at: '2026-03-22T10:00:00',
  started_at: '2026-03-22T11:00:00',
  completed_at: '',
  delivered_at: '',
  delivery_forecast: '2026-03-25',
  agreed_payment_method: 'boleto',
  agreed_payment_notes: 'Pagamento em 30 dias',
}

describe('Work Order Create Schema', () => {
  it('passes with valid minimal payload', () => {
    const r = workOrderCreateSchema.safeParse(validMinimal)
    expect(r.success).toBe(true)
    if (r.success) {
      expect(r.data.priority).toBe('normal')
      expect(r.data.is_warranty).toBe(false)
      expect(r.data.initial_status).toBe('open')
    }
  })

  it('fails when customer_id is missing', () => {
    const r = workOrderCreateSchema.safeParse({ description: 'Teste' })
    expect(r.success).toBe(false)
  })

  it('fails when description is missing', () => {
    const r = workOrderCreateSchema.safeParse({ customer_id: 1 })
    expect(r.success).toBe(false)
  })

  it('fails with invalid priority', () => {
    const r = workOrderCreateSchema.safeParse({
      ...validMinimal,
      priority: 'super_urgent',
    })
    expect(r.success).toBe(false)
  })

  it('passes with valid full payload', () => {
    const r = workOrderCreateSchema.safeParse(validFull)
    expect(r.success).toBe(true)
    if (r.success) {
      expect(r.data.priority).toBe('high')
      expect(r.data.is_warranty).toBe(true)
      expect(r.data.tags).toEqual(['urgente', 'garantia'])
    }
  })

  it('treats empty optional calibration decision fields as absent', () => {
    const r = workOrderCreateSchema.safeParse({
      ...validMinimal,
      decision_rule_agreed: '',
      decision_guard_band_mode: '',
    })

    expect(r.success).toBe(true)
    if (r.success) {
      expect(r.data.decision_rule_agreed).toBeUndefined()
      expect(r.data.decision_guard_band_mode).toBeUndefined()
    }
  })
})
