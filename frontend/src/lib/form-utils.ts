import type { UseFormSetError, FieldValues, Path } from 'react-hook-form'
import { toast } from 'sonner'
import type { AxiosError } from 'axios'

export interface ApiValidationError {
  message?: string
  errors?: Record<string, string[]>
}

export function handleFormError<T extends FieldValues>(
  error: AxiosError<ApiValidationError>,
  setError: UseFormSetError<T>,
  fallbackMessage = 'Erro ao salvar.'
) {
  const data = error?.response?.data

  if (error?.response?.status === 422 && data?.errors) {
    Object.entries(data.errors).forEach(([field, messages]) => {
      setError(field as Path<T>, { type: 'server', message: messages[0] })
    })
    toast.error(data.message || 'Verifique os campos do formulário.')
    return
  }

  toast.error(data?.message || fallbackMessage)
}
