import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { CrmAccountPlansPage } from '@/pages/crm/CrmAccountPlansPage'

const {
  mockGetAccountPlans,
  mockCreateAccountPlan,
  mockUpdateAccountPlanAction,
  mockApiGet,
} = vi.hoisted(() => ({
  mockGetAccountPlans: vi.fn(),
  mockCreateAccountPlan: vi.fn(),
  mockUpdateAccountPlanAction: vi.fn(),
  mockApiGet: vi.fn(),
}))

vi.mock('@/lib/crm-field-api', () => ({
  getAccountPlans: mockGetAccountPlans,
  createAccountPlan: mockCreateAccountPlan,
  updateAccountPlanAction: mockUpdateAccountPlanAction,
}))

vi.mock('@/lib/api', async () => {
  const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

  return {
    ...actual,
    default: {
      get: mockApiGet,
    },
  }
})

describe('CrmAccountPlansPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockGetAccountPlans.mockResolvedValue({ data: { data: [] } })
    mockCreateAccountPlan.mockResolvedValue({})
    mockUpdateAccountPlanAction.mockResolvedValue({})
    mockApiGet.mockResolvedValue({ data: { data: [] } })
  })

  it('abre o modal com meta de receita mascarada em real', async () => {
    const user = userEvent.setup()

    render(<CrmAccountPlansPage />)

    await user.click(await screen.findByRole('button', { name: /Novo Plano/i }))

    const revenueInput = screen.getByLabelText('Meta de Receita') as HTMLInputElement
    expect(revenueInput.value).toBe('R$\u00a00,00')
  })
})
