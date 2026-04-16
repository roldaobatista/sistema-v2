import { describe, expect, it } from 'vitest'

import { getQuotePaymentSummary } from '@/features/quotes/payment-summary'

describe('getQuotePaymentSummary', () => {
  it('prioriza os campos formatados vindos da API', () => {
    const summary = getQuotePaymentSummary({
      payment_terms: 'personalizado',
      payment_terms_detail: '153045-dias',
      payment_method_label: 'A combinar',
      payment_condition_summary: 'Pagamento em 3 parcelas com vencimentos programados após a emissão.',
      payment_detail_text: null,
      payment_schedule: [
        { title: '1a parcela', days: 15, due_date: '04/04/2026', text: '1a parcela: 15 dias após emissão (04/04/2026)' },
      ],
    })

    expect(summary.methodLabel).toBe('A combinar')
    expect(summary.conditionSummary).toContain('3 parcelas')
    expect(summary.schedule).toHaveLength(1)
  })

  it('faz fallback para o label bruto quando a API antiga nao enviar resumo formatado', () => {
    const summary = getQuotePaymentSummary({
      payment_terms: 'boleto_30_60',
      payment_terms_detail: null,
      payment_method_label: null,
      payment_condition_summary: null,
      payment_detail_text: null,
      payment_schedule: [],
    })

    expect(summary.methodLabel).toBe('Boleto 30 60')
    expect(summary.conditionSummary).toBe('Condição comercial a combinar com o cliente.')
  })
})
