import { describe, expect, it } from 'vitest'

import { applyQuoteTemplateDefaults } from '@/features/quotes/templates'

describe('quotes/templates', () => {
  it('aplica payment_terms_text do template ao formulario', () => {
    const result = applyQuoteTemplateDefaults(
      {
        id: 9,
        tenant_id: 1,
        name: 'Template Comercial',
        warranty_terms: '12 meses',
        payment_terms_text: '50% entrada e 50% entrega',
        general_conditions: null,
        delivery_terms: null,
        is_default: false,
        is_active: true,
      },
      '',
    )

    expect(result).toEqual({
      templateId: 9,
      paymentTermsDetail: '50% entrada e 50% entrega',
      warrantyTerms: '12 meses',
      generalConditions: '',
      deliveryTerms: '',
    })
  })

  it('preserva payment_terms_detail atual quando template nao informa esse campo', () => {
    const result = applyQuoteTemplateDefaults(
      {
        id: 11,
        tenant_id: 1,
        name: 'Template sem pagamento',
        warranty_terms: null,
        payment_terms_text: null,
        general_conditions: null,
        delivery_terms: null,
        is_default: false,
        is_active: true,
      },
      'Pagamento negociado manualmente',
    )

    expect(result).toEqual({
      templateId: 11,
      paymentTermsDetail: 'Pagamento negociado manualmente',
      warrantyTerms: '',
      generalConditions: '',
      deliveryTerms: '',
    })
  })
})
