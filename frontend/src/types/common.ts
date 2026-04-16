export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

/** Erro no estilo axios (catch de api.get/post) para acessar response?.data?.message e errors */
export type ApiErrorLike = Error & {
  response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } }
}

export interface SelectOption {
  value: string | number
  label: string
}

export interface DateRange {
  from: string | null
  to: string | null
}
