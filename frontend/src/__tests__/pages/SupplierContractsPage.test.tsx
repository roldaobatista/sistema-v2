import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { SupplierContractsPage } from '@/pages/financeiro/SupplierContractsPage'

const {
  mockApiGet,
  mockApiPost,
  mockApiPut,
  mockApiDelete,
  mockToastSuccess,
  mockToastError,
  mockHasPermission,
  mockHasRole,
} = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockApiPost: vi.fn(),
  mockApiPut: vi.fn(),
  mockApiDelete: vi.fn(),
  mockToastSuccess: vi.fn(),
  mockToastError: vi.fn(),
  mockHasPermission: vi.fn(),
  mockHasRole: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
  const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

  return {
    ...actual,
    default: {
      get: mockApiGet,
      post: mockApiPost,
      put: mockApiPut,
      delete: mockApiDelete,
    },
  }
})

vi.mock('@/stores/auth-store', () => ({
  useAuthStore: () => ({
    hasPermission: mockHasPermission,
    hasRole: mockHasRole,
  }),
}))

vi.mock('sonner', () => ({
  toast: {
    success: mockToastSuccess,
    error: mockToastError,
  },
}))

describe('SupplierContractsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockHasRole.mockReturnValue(false)
    mockHasPermission.mockImplementation((permission: string) =>
      [
        'finance.payable.view',
        'finance.payable.create',
        'finance.payable.update',
        'finance.payable.delete',
      ].includes(permission)
    )

    mockApiGet.mockImplementation((url: string) => {
      if (url === '/financial/supplier-contracts') {
        return Promise.resolve({
          data: {
            data: {
              data: [
                {
                  id: 11,
                  supplier_id: 7,
                  description: 'Contrato recorrente',
                  start_date: '2026-03-01',
                  end_date: '2026-12-31',
                  value: '1999.90',
                  payment_frequency: 'monthly',
                  auto_renew: true,
                  status: 'active',
                  supplier: { id: 7, name: 'Fornecedor Alfa' },
                },
              ],
              current_page: 1,
              last_page: 1,
              total: 1,
            },
          },
        })
      }

      if (url === '/financial/lookups/suppliers') {
        return Promise.resolve({
          data: {
            data: {
              data: [{ id: 7, name: 'Fornecedor Alfa' }],
            },
          },
        })
      }

      if (url === '/financial/lookups/supplier-contract-payment-frequencies') {
        return Promise.resolve({
          data: {
            data: {
              data: [{ id: 1, name: 'Mensal', slug: 'monthly' }],
            },
          },
        })
      }

      return Promise.resolve({ data: { data: [] } })
    })
  })

  it('renderiza contratos e lookup de frequencia com payload envelopado aninhado', async () => {
    const user = userEvent.setup()

    render(<SupplierContractsPage />)

    expect(await screen.findByText('Contrato recorrente')).toBeInTheDocument()
    expect(screen.getByText('Fornecedor Alfa')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Novo Contrato/i }))

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/suppliers', { params: { limit: 100 } })
      expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/supplier-contract-payment-frequencies')
    })

    expect(await screen.findByRole('option', { name: 'Mensal' })).toBeInTheDocument()
  })

  it('abre o formulario com valor do contrato mascarado em real', async () => {
    const user = userEvent.setup()

    render(<SupplierContractsPage />)

    await user.click(await screen.findByRole('button', { name: /Novo Contrato/i }))

    const amountInput = screen.getByLabelText('Valor (R$) *') as HTMLInputElement
    expect(amountInput.value).toBe('R$\u00a00,00')
  })
})
