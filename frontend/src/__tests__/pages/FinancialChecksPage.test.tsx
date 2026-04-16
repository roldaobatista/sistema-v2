import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { FinancialChecksPage } from '@/pages/financeiro/FinancialChecksPage'

const {
  mockApiGet,
  mockApiPost,
  mockApiPatch,
  mockHasPermission,
  mockHasRole,
} = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockApiPost: vi.fn(),
  mockApiPatch: vi.fn(),
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
      patch: mockApiPatch,
    },
  }
})

vi.mock('@/stores/auth-store', () => ({
  useAuthStore: () => ({
    hasPermission: mockHasPermission,
    hasRole: mockHasRole,
  }),
}))

describe('FinancialChecksPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockHasRole.mockReturnValue(false)
    mockHasPermission.mockImplementation((permission: string) =>
      [
        'finance.payable.view',
        'finance.payable.create',
        'finance.payable.update',
        'financeiro.view',
        'financeiro.payment.create',
      ].includes(permission)
    )

    mockApiGet.mockResolvedValue({
      data: {
        data: [],
        current_page: 1,
        last_page: 1,
        total: 0,
      },
    })
  })

  it('abre o formulario de cheque com campo monetario formatado em real', async () => {
    const user = userEvent.setup()

    render(<FinancialChecksPage />)

    await user.click(await screen.findByRole('button', { name: /Novo Cheque/i }))

    const amountInput = screen.getByLabelText('Valor (R$) *') as HTMLInputElement
    expect(amountInput.value).toBe('R$\u00a00,00')
  })
})
