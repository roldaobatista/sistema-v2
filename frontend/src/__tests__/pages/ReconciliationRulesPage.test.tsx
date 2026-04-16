import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import { ReconciliationRulesPage } from '@/pages/financeiro/ReconciliationRulesPage'

const { listMock, createMock } = vi.hoisted(() => ({
  listMock: vi.fn(),
  createMock: vi.fn(),
}))

vi.mock('@/lib/financial-api', () => ({
  financialApi: {
    reconciliationRules: {
      list: listMock,
      create: createMock,
      update: vi.fn(),
      destroy: vi.fn(),
      toggle: vi.fn(),
      test: vi.fn(),
    },
  },
}))

vi.mock('@/stores/auth-store', () => ({
  useAuthStore: () => ({
    hasPermission: () => true,
    hasRole: () => false,
  }),
}))

vi.mock('@/lib/api', async () => {
  const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

  return {
    ...actual,
    unwrapData: <T,>(value: { data?: { data?: T } } | { data?: T } | T) => {
      if (value && typeof value === 'object' && 'data' in value) {
        const payload = value.data
        if (payload && typeof payload === 'object' && 'data' in payload) {
          return payload.data as T
        }

        return payload as T
      }

      return value as T
    },
  }
})

describe('ReconciliationRulesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    listMock
      .mockResolvedValueOnce({
        data: {
          data: {
            data: [
              {
                id: 1,
                name: 'PIX antigo',
                match_field: 'description',
                match_operator: 'contains',
                match_value: 'PIX',
                match_amount_min: null,
                match_amount_max: null,
                action: 'categorize',
                category: 'Receitas',
                priority: 10,
                is_active: true,
                times_applied: 2,
              },
            ],
          },
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: {
            data: [
              {
                id: 1,
                name: 'PIX antigo',
                match_field: 'description',
                match_operator: 'contains',
                match_value: 'PIX',
                match_amount_min: null,
                match_amount_max: null,
                action: 'categorize',
                category: 'Receitas',
                priority: 10,
                is_active: true,
                times_applied: 2,
              },
              {
                id: 2,
                name: 'TED nova',
                match_field: 'description',
                match_operator: 'contains',
                match_value: 'TED',
                match_amount_min: null,
                match_amount_max: null,
                action: 'categorize',
                category: 'Receitas',
                priority: 50,
                is_active: true,
                times_applied: 0,
              },
            ],
          },
        },
      })

    createMock.mockResolvedValue({ data: { data: { id: 2 } } })
  })

  it('refaz a listagem apos criar regra para evitar cache stale', async () => {
    const user = userEvent.setup()

    render(<ReconciliationRulesPage />)

    expect(await screen.findByText(/PIX antigo/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Nova Regra/i }))
    await user.type(screen.getByPlaceholderText(/Ex: PIX Recebidos - Cliente XYZ/i), 'TED nova')
    await user.click(screen.getByRole('button', { name: /Criar Regra/i }))

    await waitFor(() => {
      expect(createMock).toHaveBeenCalledTimes(1)
    })

    await waitFor(() => {
      expect(screen.getByText(/TED nova/i)).toBeInTheDocument()
    })

    expect(listMock).toHaveBeenCalledTimes(2)
  })

  it('expõe a busca com nome acessivel para leitores de tela', async () => {
    render(<ReconciliationRulesPage />)

    await screen.findByText(/PIX antigo/i)

    expect(screen.getByRole('textbox', { name: /Buscar regras/i })).toBeInTheDocument()
  })
})
