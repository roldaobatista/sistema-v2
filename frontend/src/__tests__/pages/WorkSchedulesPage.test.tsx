import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import WorkSchedulesPage from '@/pages/rh/WorkSchedulesPage'

const { mockApiGet, mockToast } = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockToast: {
    error: vi.fn(),
    success: vi.fn(),
  },
}))

vi.mock('@/lib/api', () => ({
  default: {
    get: mockApiGet,
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  getApiErrorMessage: vi.fn((error: { response?: { data?: { message?: string } } } | undefined, fallback: string) =>
    error?.response?.data?.message ?? fallback
  ),
}))

vi.mock('@/lib/cross-tab-sync', () => ({
  broadcastQueryInvalidation: vi.fn(),
}))

vi.mock('sonner', () => ({
  toast: mockToast,
}))

describe('WorkSchedulesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('mostra erro explicito e permite retry quando a listagem falha', async () => {
    let attempts = 0

    mockApiGet.mockImplementation(() => {
      attempts += 1

      if (attempts === 1) {
        return Promise.reject({ response: { data: { message: 'Falha ao carregar escalas' } } })
      }

      return Promise.resolve({
        data: {
          data: [
            {
              id: 1,
              name: 'Escala Comercial',
              type: 'fixed',
              tolerance_minutes: 10,
              overtime_allowed: true,
              work_days: [],
            },
          ],
        },
      })
    })

    const user = userEvent.setup()

    render(<WorkSchedulesPage />)

    expect(await screen.findByText(/Erro ao carregar escalas/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Tentar novamente/i })).toBeInTheDocument()
    expect(screen.queryByText(/Nenhuma escala cadastrada/i)).not.toBeInTheDocument()

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Falha ao carregar escalas')
    })

    await user.click(screen.getByRole('button', { name: /Tentar novamente/i }))

    expect(await screen.findByText(/Escala Comercial/i)).toBeInTheDocument()
  })

  it('expõe os campos principais do formulario com nomes acessiveis', async () => {
    mockApiGet.mockResolvedValue({ data: { data: [] } })

    const user = userEvent.setup()

    render(<WorkSchedulesPage />)

    await user.click(screen.getByRole('button', { name: /Nova Escala/i }))

    expect(screen.getByRole('textbox', { name: /Nome da escala/i })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Descrição da escala/i })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /Tipo de escala/i })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: /Tolerância em minutos/i })).toBeInTheDocument()
  })
})
