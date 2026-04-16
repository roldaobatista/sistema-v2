import type {
  DebtRenegotiationFormValues,
  DebtRenegotiationPayload,
  DebtRenegotiationRecord,
} from '@/types/financial'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

type WrappedPage<T> = PaginatedResponse<T> | ApiResponse<PaginatedResponse<T>>

export function buildDebtRenegotiationPayload(input: {
  customerId: string
  receivableIds: number[]
  form: DebtRenegotiationFormValues
}): DebtRenegotiationPayload {
  const payload: DebtRenegotiationPayload = {
    customer_id: Number(input.customerId),
    receivable_ids: input.receivableIds,
    new_due_date: input.form.new_due_date,
    installments: Number(input.form.installments),
  }

  const description = input.form.description.trim()
  if (description) {
    payload.description = description
  }

  const notes = input.form.notes.trim()
  if (notes) {
    payload.notes = notes
  }

  const discountPercentage = Number(input.form.discount_percentage)
  if (discountPercentage > 0) {
    payload.discount_percentage = discountPercentage
  }

  const interestRate = Number(input.form.interest_rate)
  if (interestRate > 0) {
    payload.interest_rate = interestRate
  }

  return payload
}

export function unwrapPaginatedResponse<T>(
  response: WrappedPage<T> | undefined,
): PaginatedResponse<T> {
  if (!response) {
    return {
      data: [],
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: null,
      to: null,
    }
  }

  if (Array.isArray(response.data)) {
    return response as PaginatedResponse<T>
  }

  return response.data
}

export function unwrapDebtRenegotiationPage(
  response: WrappedPage<DebtRenegotiationRecord> | undefined,
): PaginatedResponse<DebtRenegotiationRecord> {
  return unwrapPaginatedResponse(response)
}
