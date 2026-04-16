import type { QuoteTemplate } from '@/types/quote'

interface TemplateDefaults {
  templateId: number | null
  paymentTermsDetail: string
  warrantyTerms: string
  generalConditions: string
  deliveryTerms: string
}

export function applyQuoteTemplateDefaults(
  template: QuoteTemplate | null,
  currentValues: string | {
    paymentTermsDetail?: string
    warrantyTerms?: string
    generalConditions?: string
    deliveryTerms?: string
  } = {},
): TemplateDefaults {
  const normalizedCurrentValues = typeof currentValues === 'string'
    ? { paymentTermsDetail: currentValues }
    : currentValues

  if (!template) {
    return {
      templateId: null,
      paymentTermsDetail: normalizedCurrentValues.paymentTermsDetail ?? '',
      warrantyTerms: normalizedCurrentValues.warrantyTerms ?? '',
      generalConditions: normalizedCurrentValues.generalConditions ?? '',
      deliveryTerms: normalizedCurrentValues.deliveryTerms ?? '',
    }
  }

  return {
    templateId: template.id,
    paymentTermsDetail: template.payment_terms_text?.trim() || normalizedCurrentValues.paymentTermsDetail || '',
    warrantyTerms: template.warranty_terms?.trim() || normalizedCurrentValues.warrantyTerms || '',
    generalConditions: template.general_conditions?.trim() || normalizedCurrentValues.generalConditions || '',
    deliveryTerms: template.delivery_terms?.trim() || normalizedCurrentValues.deliveryTerms || '',
  }
}
