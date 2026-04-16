import { describe, expect, it } from 'vitest'
import { buildDebtRenegotiationPayload, unwrapDebtRenegotiationPage } from '@/pages/financeiro/debt-renegotiation-utils'

describe('debt renegotiation utils', () => {
  it('monta payload no contrato atual do backend', () => {
    expect(buildDebtRenegotiationPayload({
      customerId: '9',
      receivableIds: [10, 11],
      form: {
        description: ' Acordo especial ',
        installments: '3',
        discount_percentage: '7.5',
        interest_rate: '1.2',
        new_due_date: '2026-04-10',
        notes: '  observacao final ',
      },
    })).toEqual({
      customer_id: 9,
      receivable_ids: [10, 11],
      description: 'Acordo especial',
      installments: 3,
      discount_percentage: 7.5,
      interest_rate: 1.2,
      new_due_date: '2026-04-10',
      notes: 'observacao final',
    })
  })

  it('remove campos opcionais vazios do payload', () => {
    expect(buildDebtRenegotiationPayload({
      customerId: '4',
      receivableIds: [1],
      form: {
        description: ' ',
        installments: '1',
        discount_percentage: '0',
        interest_rate: '',
        new_due_date: '2026-05-01',
        notes: '',
      },
    })).toEqual({
      customer_id: 4,
      receivable_ids: [1],
      installments: 1,
      new_due_date: '2026-05-01',
    })
  })

  it('normaliza listagem paginada embrulhada em data', () => {
    const result = unwrapDebtRenegotiationPage({
      data: {
        data: [{ id: 1, customer_id: 2, original_total: '100.00', negotiated_total: '95.00', discount_amount: '5.00', interest_amount: '0.00', fine_amount: '0.00', new_installments: 2, first_due_date: '2026-04-10', status: 'pending', created_at: '2026-03-12T10:00:00Z' }],
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 1,
        from: 1,
        to: 1,
      },
    })

    expect(result.total).toBe(1)
    expect(result.data[0]?.id).toBe(1)
  })
})
