import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { SupplierAdvancesPage } from '@/pages/financeiro/SupplierAdvancesPage'

const {
  mockApiGet,
  mockApiPost,
  mockHasPermission,
  mockHasRole,
} = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockApiPost: vi.fn(),
  mockHasPermission: vi.fn(),
  mockHasRole: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
  default: {
    get: mockApiGet,
    post: mockApiPost,
  },
}))

vi.mock('@/stores/auth-store', () => ({
  useAuthStore: () => ({
    hasPermission: mockHasPermission,
    hasRole: mockHasRole,
  }),
}))

describe('SupplierAdvancesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockHasRole.mockReturnValue(false)
    mockHasPermission.mockImplementation((permission: string) =>
      [
        'finance.payable.view',
        'finance.payable.create',
        'financeiro.view',
        'financeiro.payment.create',
      ].includes(permission)
    )

    mockApiGet.mockImplementation((url: string) => {
      if (url === '/financial/lookups/suppliers') {
        return Promise.resolve({
          data: {
            data: [{ id: 7, name: 'Fornecedor Alfa' }],
          },
        })
      }

      return Promise.resolve({
        data: {
          data: [],
          current_page: 1,
          last_page: 1,
          total: 0,
        },
      })
    })
  })

  it('abre o formulario de adiantamento com campo monetario em real', async () => {
    const user = userEvent.setup()

    render(<SupplierAdvancesPage />)

    await user.click(await screen.findByRole('button', { name: /Novo Adiantamento/i }))

    const amountInput = screen.getByLabelText('Valor (R$) *') as HTMLInputElement
    expect(amountInput.value).toBe('R$\u00a00,00')
  })
})
