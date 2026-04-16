import type { Quote } from '@/types/quote'

export interface QuotePaymentScheduleLine {
  title: string
  days: number
  due_date: string | null
  text: string
}

export interface QuotePaymentSummary {
  methodLabel: string
  conditionSummary: string
  detailText: string | null
  schedule: QuotePaymentScheduleLine[]
}

export function getQuotePaymentSummary(quote: Pick<Quote, 'payment_method_label' | 'payment_condition_summary' | 'payment_detail_text' | 'payment_schedule' | 'payment_terms' | 'payment_terms_detail'>): QuotePaymentSummary {
  const schedule = Array.isArray(quote.payment_schedule) ? quote.payment_schedule : []
  const methodLabel = quote.payment_method_label?.trim() || normalizeLabel(quote.payment_terms) || 'A combinar'
  const conditionSummary = quote.payment_condition_summary?.trim() || quote.payment_terms_detail?.trim() || 'Condição comercial a combinar com o cliente.'
  const detailText = quote.payment_detail_text?.trim() || null

  return {
    methodLabel,
    conditionSummary,
    detailText,
    schedule,
  }
}

function normalizeLabel(value?: string | null): string {
  const normalized = value?.trim()
  if (!normalized) {
    return ''
  }

  return normalized
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}
